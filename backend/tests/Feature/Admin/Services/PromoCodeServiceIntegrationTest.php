<?php

use App\Exceptions\PromoCodeInUseException;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PromoCodeService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->service = app(PromoCodeService::class);
});

test('create with admin actor writes activity row with description and causer', function (): void {
    $admin = User::factory()->admin()->create();

    $promo = $this->service->create([
        'code' => 'INTCREATE',
        'discount_type' => PromoCode::TYPE_PERCENTAGE,
        'amount' => 15,
    ], $admin);

    $log = Activity::where('description', PromoCodeService::EVENT_CREATED)
        ->where('causer_id', $admin->id)
        ->where('subject_id', $promo->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['code'])->toBe('INTCREATE');
});

test('update with admin actor writes activity row', function (): void {
    $admin = User::factory()->admin()->create();
    $promo = PromoCode::factory()->create(['amount' => 10]);

    $this->service->update($promo, ['amount' => 25], $admin);

    expect(Activity::where('description', PromoCodeService::EVENT_UPDATED)
        ->where('causer_id', $admin->id)->count())->toBe(1);
});

test('writes with null actor do not produce activity rows', function (): void {
    $this->service->create([
        'code' => 'NOACT',
        'discount_type' => PromoCode::TYPE_FIXED_CENTS,
        'amount' => 200,
    ], null);

    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('delete on a code with uses_count > 0 throws PromoCodeInUseException', function (): void {
    $admin = User::factory()->admin()->create();
    $promo = PromoCode::factory()->withUsage(2)->create();

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

test('delete on an unused code with admin actor removes the row and writes activity', function (): void {
    $admin = User::factory()->admin()->create();
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    $this->service->delete($promo, $admin);

    expect(PromoCode::find($promo->id))->toBeNull();
    expect(Activity::where('description', PromoCodeService::EVENT_DELETED)
        ->where('causer_id', $admin->id)->count())->toBe(1);
});

test('validateCode normalises uppercase and returns row only when active and not expired or over-limit', function (): void {
    PromoCode::factory()->create(['code' => 'GOOD', 'is_active' => true]);
    PromoCode::factory()->expired()->create(['code' => 'OLD']);
    PromoCode::factory()->inactive()->create(['code' => 'OFF']);
    PromoCode::factory()->withUsage(5, 5)->create(['code' => 'MAX']);

    expect($this->service->validateCode('good', 100))->not->toBeNull();
    expect($this->service->validateCode('GOOD', 100))->not->toBeNull();
    expect($this->service->validateCode('OLD', 100))->toBeNull();
    expect($this->service->validateCode('OFF', 100))->toBeNull();
    expect($this->service->validateCode('MAX', 100))->toBeNull();
    expect($this->service->validateCode('NOTHERE', 100))->toBeNull();
});

test('consume atomically increments uses_count under sequential calls', function (): void {
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    DB::transaction(fn () => $this->service->consume($promo));
    DB::transaction(fn () => $this->service->consume($promo));
    DB::transaction(fn () => $this->service->consume($promo));

    expect($promo->fresh()->uses_count)->toBe(3);
});
