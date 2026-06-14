<?php

use App\Filament\Pages\GiftCardsContent;
use App\Services\SiteSettingsService;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

test('the gift-cards api returns null until an admin saves', function (): void {
    getJson('/api/site-content/gift-cards')
        ->assertOk()
        ->assertJsonPath('data.giftCards', null);
});

test('admin and manager can open the page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(GiftCardsContent::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(GiftCardsContent::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(GiftCardsContent::canAccess())->toBeFalse();
});

test('the page prefills the built-in copy and saving serves it on the api', function (): void {
    $this->actingAsAdmin();

    Livewire::test(GiftCardsContent::class)
        ->assertSet('data.eyebrow', GiftCardsContent::GIFT_CARDS_DEFAULTS['eyebrow'])
        ->set('data.eyebrow', 'Holiday Gifting')
        ->set('data.lede', 'Give two hours in the dark.')
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/gift-cards')
        ->assertJsonPath('data.giftCards.eyebrow', 'Holiday Gifting')
        ->assertJsonPath('data.giftCards.lede', 'Give two hours in the dark.');
});

test('saving trims whitespace', function (): void {
    $this->actingAsAdmin();

    Livewire::test(GiftCardsContent::class)
        ->set('data.eyebrow', '   Padded Eyebrow   ')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SiteSettingsService::class)->get(SiteSettingsService::KEY_GIFT_CARDS_EDITORIAL)['eyebrow'])
        ->toBe('Padded Eyebrow');
});

test('a fresh mount reloads the saved copy over the defaults and the cache busts', function (): void {
    $this->actingAsAdmin();

    Livewire::test(GiftCardsContent::class)
        ->set('data.eyebrow', 'First save')
        ->call('save');

    getJson('/api/site-content/gift-cards')
        ->assertJsonPath('data.giftCards.eyebrow', 'First save');

    Livewire::test(GiftCardsContent::class)
        ->assertSet('data.eyebrow', 'First save')
        ->set('data.eyebrow', 'Second save')
        ->call('save');

    getJson('/api/site-content/gift-cards')
        ->assertJsonPath('data.giftCards.eyebrow', 'Second save');
});

test('both fields are required', function (): void {
    $this->actingAsAdmin();

    Livewire::test(GiftCardsContent::class)
        ->set('data.lede', '')
        ->call('save')
        ->assertHasErrors(['data.lede']);
});
