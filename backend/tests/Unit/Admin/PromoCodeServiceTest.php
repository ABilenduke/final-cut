<?php

use App\Exceptions\PromoCodeInUseException;
use App\Exceptions\PromoCodeNotConsumableException;
use App\Models\AdminUser;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->service = app(PromoCodeService::class);
});

test('create persists a promo code and writes activity when actor is set', function (): void {
    $admin = AdminUser::factory()->create();

    $promo = $this->service->create([
        'code' => 'hello10',
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'amount' => 10,
    ], $admin);

    // Uppercasing defence — even a lowercase input is persisted uppercase.
    // DB default for is_active is true; refresh to pick up the DB-resolved value.
    $promo->refresh();
    expect($promo->code)->toBe('HELLO10')
        ->and($promo->is_active)->toBeTrue();

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
    $admin = AdminUser::factory()->create();
    $promo = PromoCode::factory()->create(['amount' => 10]);

    $updated = $this->service->update($promo, ['amount' => 20], $admin);

    expect($updated->amount)->toBe(20);
    expect(Activity::where('description', PromoCodeService::EVENT_UPDATED)->count())->toBe(1);
});

test('deactivate sets is_active false and logs', function (): void {
    $admin = AdminUser::factory()->create();
    $promo = PromoCode::factory()->create(['is_active' => true]);

    $this->service->deactivate($promo, $admin);

    expect($promo->fresh()->is_active)->toBeFalse();
    expect(Activity::where('description', PromoCodeService::EVENT_DEACTIVATED)->count())->toBe(1);
});

test('delete removes row when uses_count is zero', function (): void {
    $admin = AdminUser::factory()->create();
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    $this->service->delete($promo, $admin);

    expect(PromoCode::find($promo->id))->toBeNull();
    expect(Activity::where('description', PromoCodeService::EVENT_DELETED)->count())->toBe(1);
});

test('delete throws PromoCodeInUseException when uses_count > 0', function (): void {
    $admin = AdminUser::factory()->create();
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
    PromoCode::factory()->create(['code' => 'SUMMER25', 'is_active' => true]);

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
