<?php

use App\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\EditPromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use App\Models\AdminUser;
use App\Models\PromoCode;
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

test('creating a promo routes through PromoCodeService with the admin actor', function (): void {
    // The redirect after CreateRecord needs a real id; persist with a
    // different code so the form's unique() check doesn't flag a collision.
    $expected = PromoCode::factory()->create(['code' => 'STUB-RETURN']);

    $capturedData = null;
    $capturedActor = null;

    $service = $this->mock(PromoCodeService::class);
    $service->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (array $data, ?AdminUser $actor) use (&$capturedData, &$capturedActor, $expected) {
            $capturedData = $data;
            $capturedActor = $actor;

            return $expected;
        });

    Livewire::test(CreatePromoCode::class)
        ->set('data.code', 'new20')
        ->set('data.discount_type', PromoCode::TYPE_PERCENTAGE)
        ->set('data.amount', 20)
        ->set('data.is_active', true)
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
        ->andReturnUsing(function ($record, array $data, ?AdminUser $actor) use (&$capturedActor, $promo) {
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
    $promo = PromoCode::factory()->create(['is_active' => true]);

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
