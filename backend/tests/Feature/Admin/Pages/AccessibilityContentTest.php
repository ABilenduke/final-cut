<?php

use App\Filament\Pages\AccessibilityContent;
use App\Services\SiteSettingsService;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

test('the accessibility api returns null until an admin saves', function (): void {
    getJson('/api/site-content/accessibility')
        ->assertOk()
        ->assertJsonPath('data.accessibility', null);
});

test('admin and manager can open the accessibility content page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(AccessibilityContent::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(AccessibilityContent::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(AccessibilityContent::canAccess())->toBeFalse();
});

test('the page prefills the built-in prose and saving serves it on the api', function (): void {
    $this->actingAsAdmin();

    Livewire::test(AccessibilityContent::class)
        ->assertSet('data.intro', AccessibilityContent::STATEMENT_DEFAULTS['intro'])
        ->set('data.serviceAnimals', 'Trained service dogs welcome throughout.')
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/accessibility')
        ->assertJsonPath('data.accessibility.serviceAnimals', 'Trained service dogs welcome throughout.')
        // Untouched fields persist their defaults.
        ->assertJsonPath('data.accessibility.intro', AccessibilityContent::STATEMENT_DEFAULTS['intro']);
});

test('saving trims whitespace', function (): void {
    $this->actingAsAdmin();

    Livewire::test(AccessibilityContent::class)
        ->set('data.wheelchairSeating', '   Padded seating note   ')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SiteSettingsService::class)->get(SiteSettingsService::KEY_ACCESSIBILITY_STATEMENT)['wheelchairSeating'])
        ->toBe('Padded seating note');
});

test('a fresh mount reloads saved prose over the defaults and the cache busts', function (): void {
    $this->actingAsAdmin();

    Livewire::test(AccessibilityContent::class)
        ->set('data.intro', 'First save')
        ->call('save');

    getJson('/api/site-content/accessibility')
        ->assertJsonPath('data.accessibility.intro', 'First save');

    Livewire::test(AccessibilityContent::class)
        ->assertSet('data.intro', 'First save')
        ->set('data.intro', 'Second save')
        ->call('save');

    getJson('/api/site-content/accessibility')
        ->assertJsonPath('data.accessibility.intro', 'Second save');
});

test('every section field is required', function (): void {
    $this->actingAsAdmin();

    Livewire::test(AccessibilityContent::class)
        ->set('data.audioDescription', '')
        ->call('save')
        ->assertHasErrors(['data.audioDescription']);
});
