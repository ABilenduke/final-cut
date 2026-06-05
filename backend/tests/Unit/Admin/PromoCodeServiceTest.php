<?php

use App\Enums\BookingStatus;
use App\Exceptions\PromoCodeInUseException;
use App\Exceptions\PromoCodeNotConsumableException;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PromoCodeService;
use App\Services\PromoRedemptionIdentity;
use Spatie\Activitylog\Models\Activity;

/** Seed a prior redemption of $promo by a user or guest at a given status. */
function seedRedemption(PromoCode $promo, BookingStatus $status, ?string $userId = null, ?string $guestEmail = null): Booking
{
    return Booking::factory()->create([
        'promo_code_id' => $promo->id,
        'user_id' => $userId,
        'guest_email' => $guestEmail,
        'status' => $status,
    ]);
}

beforeEach(function (): void {
    $this->service = app(PromoCodeService::class);
});

test('create persists a promo code and writes activity when actor is set', function (): void {
    $admin = User::factory()->admin()->create();

    $promo = $this->service->create([
        'code' => 'hello10',
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'amount' => 10,
    ], $admin);

    // Uppercasing defence — even a lowercase input is persisted uppercase.
    // deactivated_at defaults to NULL, so the is_active accessor is true.
    $promo->refresh();
    expect($promo->code)->toBe('HELLO10')
        ->and($promo->is_active)->toBeTrue()
        ->and($promo->deactivated_at)->toBeNull();

    expect(Activity::where('log_name', 'admin')->where('description', PromoCodeService::EVENT_CREATED)->count())
        ->toBe(1);
});

test('create writes no activity when actor is null (customer-path safeguard)', function (): void {
    $this->service->create([
        'code' => 'NOACTOR',
        'discount_type' => PromoCode::TYPE_FIXED_CENTS,
        'amount' => 500,
    ], null);

    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('update persists changes and logs with actor', function (): void {
    $admin = User::factory()->admin()->create();
    $promo = PromoCode::factory()->create(['amount' => 10]);

    $updated = $this->service->update($promo, ['amount' => 20], $admin);

    expect($updated->amount)->toBe(20);
    expect(Activity::where('description', PromoCodeService::EVENT_UPDATED)->count())->toBe(1);
});

test('deactivate stamps deactivated_at, flips is_active false, and logs', function (): void {
    $admin = User::factory()->admin()->create();
    $promo = PromoCode::factory()->create(); // active by default (deactivated_at = null)

    $this->service->deactivate($promo, $admin);

    $fresh = $promo->fresh();
    expect($fresh->is_active)->toBeFalse()
        // The whole point of the nullable-timestamp convention: free WHEN metadata.
        ->and($fresh->deactivated_at)->not->toBeNull()
        ->and($fresh->deactivated_at->isToday())->toBeTrue();
    expect(Activity::where('description', PromoCodeService::EVENT_DEACTIVATED)->count())->toBe(1);
});

test('delete removes row when uses_count is zero', function (): void {
    $admin = User::factory()->admin()->create();
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    $this->service->delete($promo, $admin);

    expect(PromoCode::find($promo->id))->toBeNull();
    expect(Activity::where('description', PromoCodeService::EVENT_DELETED)->count())->toBe(1);
});

test('delete throws PromoCodeInUseException when uses_count > 0', function (): void {
    $admin = User::factory()->admin()->create();
    $promo = PromoCode::factory()->withUsage(3)->create();

    $caught = null;
    try {
        $this->service->delete($promo, $admin);
    } catch (PromoCodeInUseException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull();
    expect(PromoCode::find($promo->id))->not->toBeNull();
    expect(Activity::where('description', PromoCodeService::EVENT_DELETED)->count())->toBe(0);
});

test('validateCode normalises input to uppercase and returns active non-expired row', function (): void {
    PromoCode::factory()->create(['code' => 'SUMMER25', 'deactivated_at' => null]);

    $result = $this->service->validateCode('summer25', 5000);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe('SUMMER25');
});

test('validateCode returns null for inactive code', function (): void {
    PromoCode::factory()->inactive()->create(['code' => 'OFFLINE']);

    expect($this->service->validateCode('OFFLINE', 5000))->toBeNull();
});

test('validateCode returns null for expired code', function (): void {
    PromoCode::factory()->expired()->create(['code' => 'PAST']);

    expect($this->service->validateCode('PAST', 5000))->toBeNull();
});

test('validateCode returns null when usage_limit reached', function (): void {
    PromoCode::factory()->withUsage(10, 10)->create(['code' => 'MAXED']);

    expect($this->service->validateCode('MAXED', 5000))->toBeNull();
});

test('validateCode returns null for unknown code', function (): void {
    expect($this->service->validateCode('DOESNOTEXIST', 5000))->toBeNull();
});

test('validateCode returns null for empty code', function (): void {
    expect($this->service->validateCode('   ', 5000))->toBeNull();
    expect($this->service->validateCode('', 5000))->toBeNull();
});

test('consume atomically increments uses_count under lock', function (): void {
    $promo = PromoCode::factory()->create(['uses_count' => 5]);

    $this->service->consume($promo);

    expect($promo->fresh()->uses_count)->toBe(6);
});

test('consume does not write activity when actor is null', function (): void {
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    $this->service->consume($promo, null);

    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('consume throws REASON_INACTIVE when the locked row is no longer active', function (): void {
    $promo = PromoCode::factory()->inactive()->create();

    $caught = null;
    try {
        $this->service->consume($promo);
    } catch (PromoCodeNotConsumableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(PromoCodeNotConsumableException::REASON_INACTIVE);
    expect($promo->fresh()->uses_count)->toBe(0);
});

test('consume throws REASON_EXPIRED when the locked row is expired', function (): void {
    $promo = PromoCode::factory()->expired()->create();

    $caught = null;
    try {
        $this->service->consume($promo);
    } catch (PromoCodeNotConsumableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(PromoCodeNotConsumableException::REASON_EXPIRED);
});

test('consume throws REASON_LIMIT_REACHED when uses_count caught up to usage_limit between validate and consume', function (): void {
    // Simulate the race: caller validated against (uses_count = 4, limit = 5),
    // then a concurrent confirmation pushed uses_count to 5 before this
    // consume took its lock. The locked re-read sees the new state and
    // refuses to over-consume.
    $promo = PromoCode::factory()->withUsage(4, 5)->create();
    PromoCode::query()->whereKey($promo->id)->update(['uses_count' => 5]);

    $caught = null;
    try {
        $this->service->consume($promo);
    } catch (PromoCodeNotConsumableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(PromoCodeNotConsumableException::REASON_LIMIT_REACHED);
    expect($promo->fresh()->uses_count)->toBe(5);
});

test('consume throws REASON_NOT_FOUND when the row was deleted before the lock', function (): void {
    $promo = PromoCode::factory()->create();
    PromoCode::query()->whereKey($promo->id)->delete();

    $caught = null;
    try {
        $this->service->consume($promo);
    } catch (PromoCodeNotConsumableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(PromoCodeNotConsumableException::REASON_NOT_FOUND);
});

/*
|--------------------------------------------------------------------------
| per_user_limit enforcement (consume + validateCode)
|--------------------------------------------------------------------------
*/

test('consume with a per_user_limit but null identity still increments (back-compat)', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    $user = User::factory()->create();
    seedRedemption($promo, BookingStatus::Confirmed, userId: $user->id);

    // No identity → per-user check skipped; only the (null) usage_limit applies.
    $this->service->consume($promo);

    expect($promo->refresh()->uses_count)->toBe(1);
});

test('consume throws REASON_PER_USER_LIMIT when the user is at their cap and does not increment', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    $user = User::factory()->create();
    seedRedemption($promo, BookingStatus::Confirmed, userId: $user->id);

    $caught = null;
    try {
        $this->service->consume($promo, identity: new PromoRedemptionIdentity(userId: $user->id));
    } catch (PromoCodeNotConsumableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(PromoCodeNotConsumableException::REASON_PER_USER_LIMIT);
    expect($promo->refresh()->uses_count)->toBe(0); // rolled back
});

test('consume enforces the cap for guests keyed on guest_email', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    seedRedemption($promo, BookingStatus::Confirmed, guestEmail: 'guest@example.com');

    $caught = null;
    try {
        $this->service->consume($promo, identity: new PromoRedemptionIdentity(guestEmail: 'guest@example.com'));
    } catch (PromoCodeNotConsumableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(PromoCodeNotConsumableException::REASON_PER_USER_LIMIT);
});

test('guest redemption counting is case-insensitive', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    seedRedemption($promo, BookingStatus::Confirmed, guestEmail: 'guest@example.com');

    expect(fn () => $this->service->consume($promo, identity: new PromoRedemptionIdentity(guestEmail: 'GUEST@EXAMPLE.COM')))
        ->toThrow(PromoCodeNotConsumableException::class);
});

test('a prior Refunded redemption still counts (a refund does not reopen the slot)', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    $user = User::factory()->create();
    seedRedemption($promo, BookingStatus::Refunded, userId: $user->id);

    expect(fn () => $this->service->consume($promo, identity: new PromoRedemptionIdentity(userId: $user->id)))
        ->toThrow(PromoCodeNotConsumableException::class);
});

test('prior Cancelled and Held redemptions do NOT count toward the cap', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    $user = User::factory()->create();
    seedRedemption($promo, BookingStatus::Cancelled, userId: $user->id);
    seedRedemption($promo, BookingStatus::Held, userId: $user->id);

    // Neither counts → consume succeeds and increments.
    $this->service->consume($promo, identity: new PromoRedemptionIdentity(userId: $user->id));

    expect($promo->refresh()->uses_count)->toBe(1);
});

test('the redemption count is scoped to this promo only', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    $other = PromoCode::factory()->create(['per_user_limit' => 1]);
    $user = User::factory()->create();
    seedRedemption($other, BookingStatus::Confirmed, userId: $user->id);

    // A redemption of a DIFFERENT promo is irrelevant.
    $this->service->consume($promo, identity: new PromoRedemptionIdentity(userId: $user->id));

    expect($promo->refresh()->uses_count)->toBe(1);
});

test('excludeBookingId removes the named booking from the count', function (): void {
    $promo = PromoCode::factory()->create(['per_user_limit' => 1]);
    $user = User::factory()->create();
    $current = seedRedemption($promo, BookingStatus::Confirmed, userId: $user->id);

    // The current in-flight booking is already persisted with this promo; exclude
    // it so it does not count itself.
    $this->service->consume($promo, identity: new PromoRedemptionIdentity(userId: $user->id, excludeBookingId: $current->id));

    expect($promo->refresh()->uses_count)->toBe(1);
});

test('validateCode returns null when the identity is already at the per_user_limit', function (): void {
    $promo = PromoCode::factory()->create(['code' => 'CAP1', 'per_user_limit' => 1]);
    $user = User::factory()->create();
    seedRedemption($promo, BookingStatus::Confirmed, userId: $user->id);

    expect($this->service->validateCode('CAP1', 0, new PromoRedemptionIdentity(userId: $user->id)))->toBeNull();
});

test('validateCode ignores per_user_limit when no identity is supplied (two-arg back-compat)', function (): void {
    $promo = PromoCode::factory()->create(['code' => 'CAP2', 'per_user_limit' => 1]);
    $user = User::factory()->create();
    seedRedemption($promo, BookingStatus::Confirmed, userId: $user->id);

    expect($this->service->validateCode('CAP2', 0))->not->toBeNull();
});
