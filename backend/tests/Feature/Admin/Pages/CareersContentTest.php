<?php

use App\Filament\Pages\CareersContent;
use App\Services\SiteSettingsService;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

/**
 * The "benefits" field is a simple Repeater; its Livewire state is a
 * key→['benefit' => string] map (the keys are item ids — any stable strings
 * work in a test). getState() dehydrates it back to a flat ordered list.
 *
 * @param  list<string>  $benefits
 * @return array<string, array{benefit: string}>
 */
function repeaterBenefits(array $benefits): array
{
    $state = [];
    foreach (array_values($benefits) as $i => $benefit) {
        $state["item-{$i}"] = ['benefit' => $benefit];
    }

    return $state;
}

test('the careers content api returns null benefits until an admin saves', function (): void {
    getJson('/api/site-content/careers')
        ->assertOk()
        ->assertJsonPath('data.benefits', null);
});

test('admin and manager can open the careers content page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(CareersContent::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(CareersContent::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(CareersContent::canAccess())->toBeFalse();
});

test('mount prefills the built-in benefits, so an unchanged save persists the defaults', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CareersContent::class)
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/careers')
        ->assertJsonPath('data.benefits', CareersContent::BENEFIT_DEFAULTS);
});

test('saving custom benefits serves them on the api', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CareersContent::class)
        ->set('data.benefits', repeaterBenefits(['Free tickets', 'Premier membership', 'Flexible shifts']))
        ->call('save')
        ->assertHasNoErrors();

    getJson('/api/site-content/careers')
        ->assertJsonPath('data.benefits', ['Free tickets', 'Premier membership', 'Flexible shifts']);
});

test('saving drops blank rows and trims whitespace', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CareersContent::class)
        ->set('data.benefits', repeaterBenefits(['  Padded benefit  ', '', '   ', 'Second']))
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SiteSettingsService::class)->get(SiteSettingsService::KEY_CAREERS_BENEFITS)['benefits'])
        ->toBe(['Padded benefit', 'Second']);
});

test('a fresh mount reloads the saved benefits over the defaults and the cache busts', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CareersContent::class)
        ->set('data.benefits', repeaterBenefits(['First save']))
        ->call('save');

    getJson('/api/site-content/careers')
        ->assertJsonPath('data.benefits', ['First save']);

    // A fresh page instance: mount must prefill the SAVED value, not the
    // defaults — an unchanged save then re-persists 'First save'.
    Livewire::test(CareersContent::class)
        ->call('save');

    getJson('/api/site-content/careers')
        ->assertJsonPath('data.benefits', ['First save']);
});
