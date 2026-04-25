<?php

use App\Exceptions\PromoCodeInUseException;
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

test('incrementUsage atomically increments uses_count', function (): void {
    $promo = PromoCode::factory()->create(['uses_count' => 5]);

    $this->service->incrementUsage($promo);

    expect($promo->fresh()->uses_count)->toBe(6);
});

test('incrementUsage does not write activity when actor is null', function (): void {
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    $this->service->incrementUsage($promo, null);

    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});
