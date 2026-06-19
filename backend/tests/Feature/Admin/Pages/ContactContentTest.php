<?php

use App\Filament\Pages\ContactContent;
use App\Services\SiteSettingsService;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

test('the contact-info api returns null until an admin saves', function (): void {
    getJson('/api/site-content/contact-info')
        ->assertOk()
        ->assertJsonPath('data.contactInfo', null);
});

test('admin and manager can open the contact content page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(ContactContent::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(ContactContent::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(ContactContent::canAccess())->toBeFalse();
});

test('the page prefills the built-in prose and saving serves it on the api', function (): void {
    $this->actingAsAdmin();

    Livewire::test(ContactContent::class)
        ->assertSet('data.byCar', ContactContent::CONTACT_INFO_DEFAULTS['byCar'])
        ->set('data.byCar', 'Valet only on weekends.')
        ->set('data.byTransit', 'Tram line 9 stops at the door.')
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/contact-info')
        ->assertJsonPath('data.contactInfo.byCar', 'Valet only on weekends.')
        ->assertJsonPath('data.contactInfo.byTransit', 'Tram line 9 stops at the door.')
        ->assertJsonPath('data.contactInfo.accessibility', ContactContent::CONTACT_INFO_DEFAULTS['accessibility']);
});

test('saving trims whitespace', function (): void {
    $this->actingAsAdmin();

    Livewire::test(ContactContent::class)
        ->set('data.byCar', '   Padded directions   ')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SiteSettingsService::class)->get(SiteSettingsService::KEY_CONTACT_INFO)['byCar'])
        ->toBe('Padded directions');
});

test('a fresh mount reloads saved prose over the defaults and the cache busts', function (): void {
    $this->actingAsAdmin();

    Livewire::test(ContactContent::class)
        ->set('data.byTransit', 'First save')
        ->call('save');

    getJson('/api/site-content/contact-info')
        ->assertJsonPath('data.contactInfo.byTransit', 'First save');

    Livewire::test(ContactContent::class)
        ->assertSet('data.byTransit', 'First save')
        ->set('data.byTransit', 'Second save')
        ->call('save');

    getJson('/api/site-content/contact-info')
        ->assertJsonPath('data.contactInfo.byTransit', 'Second save');
});

test('all three fields are required', function (): void {
    $this->actingAsAdmin();

    Livewire::test(ContactContent::class)
        ->set('data.byCar', '')
        ->call('save')
        ->assertHasErrors(['data.byCar']);
});
