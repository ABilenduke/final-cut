<?php

use App\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\EditPromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\PromoCodeService;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('admin can see the promo list', function (): void {
    $promos = PromoCode::factory()->count(3)->create();

    Livewire::test(ListPromoCodes::class)
        ->assertCanSeeTableRecords($promos);
});

test('the promo form exposes per_user_limit now that enforcement ships', function (): void {
    // per_user_limit is enforced by PromoCodeService (by account id or
    // normalized guest email), so the control is no longer misleading.
    Livewire::test(CreatePromoCode::class)
        ->assertFormFieldExists('per_user_limit');
});

test('a per_user_limit set on the create form reaches the PromoCodeService payload', function (): void {
    $expected = PromoCode::factory()->create(['code' => 'STUB-PUL']);

    $captured = null;
    $service = $this->mock(PromoCodeService::class);
    $service->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (array $data, ?User $actor) use (&$captured, $expected) {
            $captured = $data;

            return $expected;
        });

    Livewire::test(CreatePromoCode::class)
        ->set('data.code', 'pul3')
        ->set('data.discount_type', PromoCode::TYPE_PERCENTAGE)
        ->set('data.amount', 20)
        ->set('data.per_user_limit', 3)
        ->call('create')
        ->assertHasNoFormErrors();

    // Loose compare: a numeric TextInput may dehydrate as a string.
    expect($captured['per_user_limit'])->toEqual(3);
});

test('creating a promo routes through PromoCodeService with the admin actor', function (): void {
    // The redirect after CreateRecord needs a real id; persist with a
    // different code so the form's unique() check doesn't flag a collision.
    $expected = PromoCode::factory()->create(['code' => 'STUB-RETURN']);

    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(PromoCodeService::class);
    $service->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (array $data, ?User $actor) use (&$capturedData, &$capturedActor, $expected) {
            $capturedData = $data;
            $capturedActor = $actor;

            return $expected;
        });

    Livewire::test(CreatePromoCode::class)
        ->set('data.code', 'new20')
        ->set('data.discount_type', PromoCode::TYPE_PERCENTAGE)
        ->set('data.amount', 20)
        ->call('create')
        ->assertHasNoFormErrors();

    // Uppercase normalisation applied in form dehydrate hook.
    expect($capturedData['code'])->toBe('NEW20');
    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('editing a promo routes through PromoCodeService with the admin actor', function (): void {
    $promo = PromoCode::factory()->create(['amount' => 10]);

    $capturedActor = null;
    $service = $this->mock(PromoCodeService::class);
    $service->shouldReceive('update')
        ->once()
        ->andReturnUsing(function ($record, array $data, ?User $actor) use (&$capturedActor, $promo) {
            $capturedActor = $actor;

            return $promo;
        });

    Livewire::test(EditPromoCode::class, ['record' => $promo->getRouteKey()])
        ->set('data.amount', 25)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($capturedActor?->id)->toBe($this->admin->id);
});

test('deactivate action routes through PromoCodeService', function (): void {
    $promo = PromoCode::factory()->create(['deactivated_at' => null]);

    $service = $this->mock(PromoCodeService::class);
    $service->shouldReceive('deactivate')->once();

    Livewire::test(ListPromoCodes::class)
        ->callTableAction('deactivate', $promo);
});

test('delete action is visible when uses_count is zero', function (): void {
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    Livewire::test(ListPromoCodes::class)
        ->assertTableActionVisible('delete', $promo);
});

test('delete action is hidden when uses_count > 0', function (): void {
    $promo = PromoCode::factory()->withUsage(3)->create();

    Livewire::test(ListPromoCodes::class)
        ->assertTableActionHidden('delete', $promo);
});

test('delete action routes through PromoCodeService — stock DeleteAction regression guard', function (): void {
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    $service = $this->mock(PromoCodeService::class);
    // `->using()` wires the action through the service — if someone removes
    // the ->using() block, the default DeleteAction calls $record->delete()
    // directly and this expectation fails.
    $service->shouldReceive('delete')->once();

    Livewire::test(ListPromoCodes::class)
        ->callTableAction('delete', $promo);
});
