<?php

use App\Filament\Resources\PromoCodeResource;
use App\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use App\Models\PromoCode;

test('ops can view the promo list but cannot create, edit, deactivate, or delete', function (): void {
    $this->actingAsOps();
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    expect(PromoCodeResource::canViewAny())->toBeTrue();
    expect(PromoCodeResource::canCreate())->toBeFalse();
    expect(PromoCodeResource::canEdit($promo))->toBeFalse();
    expect(PromoCodeResource::canDelete($promo))->toBeFalse();

    $this->get(CreatePromoCode::getUrl())->assertForbidden();
});

test('manager can create, edit, deactivate, and delete promo codes', function (): void {
    $this->actingAsManager();
    $promo = PromoCode::factory()->create(['uses_count' => 0]);

    expect(PromoCodeResource::canViewAny())->toBeTrue();
    expect(PromoCodeResource::canCreate())->toBeTrue();
    expect(PromoCodeResource::canEdit($promo))->toBeTrue();
    expect(PromoCodeResource::canDelete($promo))->toBeTrue();
});

test('nobody role cannot see promo list', function (): void {
    $this->actingAsNobody();

    expect(PromoCodeResource::canViewAny())->toBeFalse();

    $this->get(ListPromoCodes::getUrl())->assertForbidden();
});
