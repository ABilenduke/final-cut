<?php

use App\Filament\Pages\PrivateScreeningsContent;
use App\Services\SiteSettingsService;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

test('the private-screenings api returns null until an admin saves', function (): void {
    getJson('/api/site-content/private-screenings')
        ->assertOk()
        ->assertJsonPath('data.privateScreenings', null);
});

test('admin and manager can open the page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(PrivateScreeningsContent::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(PrivateScreeningsContent::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(PrivateScreeningsContent::canAccess())->toBeFalse();
});

test('the page prefills the built-in copy and saving serves it on the api', function (): void {
    $this->actingAsAdmin();

    Livewire::test(PrivateScreeningsContent::class)
        ->assertSet('data.title', PrivateScreeningsContent::SCREENINGS_DEFAULTS['title'])
        ->set('data.title', 'Host Your Premiere')
        ->set('data.intro', 'Book the whole house.')
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/private-screenings')
        ->assertJsonPath('data.privateScreenings.title', 'Host Your Premiere')
        ->assertJsonPath('data.privateScreenings.intro', 'Book the whole house.');
});

test('saving trims whitespace', function (): void {
    $this->actingAsAdmin();

    Livewire::test(PrivateScreeningsContent::class)
        ->set('data.title', '   Padded Title   ')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SiteSettingsService::class)->get(SiteSettingsService::KEY_PRIVATE_SCREENINGS)['title'])
        ->toBe('Padded Title');
});

test('a fresh mount reloads the saved copy over the defaults and the cache busts', function (): void {
    $this->actingAsAdmin();

    Livewire::test(PrivateScreeningsContent::class)
        ->set('data.title', 'First save')
        ->call('save');

    getJson('/api/site-content/private-screenings')
        ->assertJsonPath('data.privateScreenings.title', 'First save');

    Livewire::test(PrivateScreeningsContent::class)
        ->assertSet('data.title', 'First save')
        ->set('data.title', 'Second save')
        ->call('save');

    getJson('/api/site-content/private-screenings')
        ->assertJsonPath('data.privateScreenings.title', 'Second save');
});

test('both fields are required', function (): void {
    $this->actingAsAdmin();

    Livewire::test(PrivateScreeningsContent::class)
        ->set('data.intro', '')
        ->call('save')
        ->assertHasErrors(['data.intro']);
});
