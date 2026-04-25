<?php

use App\Filament\Resources\GiftCardResource;
use App\Filament\Resources\GiftCardResource\Pages\ListGiftCards;
use App\Models\GiftCard;
use Livewire\Livewire;

test('ops can view the gift card list but cannot void', function (): void {
    $this->actingAsOps();
    $card = GiftCard::factory()->active()->create();

    expect(GiftCardResource::canViewAny())->toBeTrue();

    Livewire::test(ListGiftCards::class)
        ->assertTableActionHidden('void', $card);
});

test('manager can view and void gift cards (manager has gift_cards.void)', function (): void {
    $this->actingAsManager();
    $card = GiftCard::factory()->active()->create();

    expect(GiftCardResource::canViewAny())->toBeTrue();

    Livewire::test(ListGiftCards::class)
        ->assertTableActionVisible('void', $card);
});

test('admin can void gift cards', function (): void {
    $this->actingAsAdmin();
    $card = GiftCard::factory()->active()->create();

    Livewire::test(ListGiftCards::class)
        ->assertTableActionVisible('void', $card);
});

test('nobody role cannot see the gift card list', function (): void {
    $this->actingAsNobody();

    expect(GiftCardResource::canViewAny())->toBeFalse();

    $this->get(ListGiftCards::getUrl())->assertForbidden();
});
