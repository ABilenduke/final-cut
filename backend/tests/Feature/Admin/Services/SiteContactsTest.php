<?php

use App\Filament\Pages\SiteContacts;
use App\Services\SiteSettingsService;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

test('the contacts api returns null until an admin saves', function (): void {
    getJson('/api/site-content/contacts')
        ->assertOk()
        ->assertJsonPath('data.contacts', null);
});

test('admin and manager can open the site contacts page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(SiteContacts::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(SiteContacts::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(SiteContacts::canAccess())->toBeFalse();
});

test('the page prefills the built-in contacts and saving serves them on the api', function (): void {
    $this->actingAsAdmin();

    Livewire::test(SiteContacts::class)
        ->assertSet('data.footerVenueName', 'Final Cut Theatre')
        ->assertSet('data.conciergeEmail', 'concierge@finalcut.test')
        ->set('data.footerPhone', '(555) 999-0000')
        ->set('data.accessibilityEmail', 'access@finalcut.test')
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/contacts')
        ->assertJsonPath('data.contacts.footerPhone', '(555) 999-0000')
        ->assertJsonPath('data.contacts.accessibilityEmail', 'access@finalcut.test')
        ->assertJsonPath('data.contacts.privacyEmail', 'privacy@finalcut.test');
});

test('the page reloads saved values over the defaults and the cache busts', function (): void {
    $admin = $this->actingAsAdmin();
    $service = app(SiteSettingsService::class);

    Livewire::test(SiteContacts::class)
        ->set('data.generalEmail', 'first@finalcut.test')
        ->call('save');

    getJson('/api/site-content/contacts')
        ->assertJsonPath('data.contacts.generalEmail', 'first@finalcut.test');

    Livewire::test(SiteContacts::class)
        ->assertSet('data.generalEmail', 'first@finalcut.test')
        ->set('data.generalEmail', 'second@finalcut.test')
        ->call('save');

    getJson('/api/site-content/contacts')
        ->assertJsonPath('data.contacts.generalEmail', 'second@finalcut.test');

    expect($service->get(SiteSettingsService::KEY_SITE_CONTACTS)['generalEmail'])
        ->toBe('second@finalcut.test');
});

test('emails are validated', function (): void {
    $this->actingAsAdmin();

    Livewire::test(SiteContacts::class)
        ->set('data.conciergeEmail', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['data.conciergeEmail']);
});
