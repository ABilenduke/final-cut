<?php

use App\Filament\Pages\HomePageContent;
use App\Models\SiteSetting;
use App\Services\SiteSettingsService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\getJson;

function membershipBlob(array $overrides = []): array
{
    return array_merge([
        'eyebrow' => 'Membership',
        'title' => 'Join the',
        'titleEmphasis' => 'Reel Society.',
        'copy' => 'A monthly membership for film people.',
        'priceLabel' => 'Become a Member · $24/mo',
        'ctaLabel' => 'View all tiers',
        'cardTier' => 'Charter Member',
        'cardNumber' => 'No. 0047',
        'cardValidThrough' => 'Valid through 12 · 2027',
        'cardSociety' => 'Reel Society',
        'cardTitle' => 'Final',
        'cardTitleEmphasis' => 'Cut.',
        'perks' => [
            ['title' => 'Unlimited screenings', 'detail' => 'Every film, every night.'],
        ],
    ], $overrides);
}

test('the service round-trips a blob, stamps the actor, and logs activity', function (): void {
    $admin = $this->actingAsAdmin();
    $service = app(SiteSettingsService::class);

    expect($service->get(SiteSettingsService::KEY_HOME_MEMBERSHIP))->toBeNull();

    $service->set(SiteSettingsService::KEY_HOME_MEMBERSHIP, membershipBlob(), $admin);

    $stored = $service->get(SiteSettingsService::KEY_HOME_MEMBERSHIP);
    expect($stored)->toBeArray()
        ->and($stored['titleEmphasis'])->toBe('Reel Society.')
        ->and(SiteSetting::query()->find(SiteSettingsService::KEY_HOME_MEMBERSHIP)->updated_by)
        ->toBe($admin->id);

    $activity = Activity::query()
        ->where('description', SiteSettingsService::EVENT_UPDATED)
        ->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe((string) $admin->id)
        ->and($activity->properties['key'])->toBe(SiteSettingsService::KEY_HOME_MEMBERSHIP);
});

test('the api returns null membership until an admin saves one', function (): void {
    getJson('/api/site-content/home')
        ->assertOk()
        ->assertJsonPath('data.membership', null);
});

test('the api serves the saved blob and the cache busts on set', function (): void {
    $admin = $this->actingAsAdmin();
    $service = app(SiteSettingsService::class);

    getJson('/api/site-content/home')->assertJsonPath('data.membership', null);

    $service->set(SiteSettingsService::KEY_HOME_MEMBERSHIP, membershipBlob(), $admin);
    getJson('/api/site-content/home')
        ->assertJsonPath('data.membership.priceLabel', 'Become a Member · $24/mo');

    $service->set(
        SiteSettingsService::KEY_HOME_MEMBERSHIP,
        membershipBlob(['priceLabel' => 'Now $29/mo']),
        $admin,
    );
    getJson('/api/site-content/home')
        ->assertJsonPath('data.membership.priceLabel', 'Now $29/mo');
});

test('admin and manager can open the home page content page; ops cannot', function (): void {
    $this->actingAsAdmin();
    expect(HomePageContent::canAccess())->toBeTrue();

    $this->actingAsManager();
    expect(HomePageContent::canAccess())->toBeTrue();

    $this->actingAsOps();
    expect(HomePageContent::canAccess())->toBeFalse();
});

test('the page prefills the built-in copy and saving persists through the service', function (): void {
    $this->actingAsAdmin();

    Livewire::test(HomePageContent::class)
        ->assertSet('data.eyebrow', 'Membership')
        ->set('data.copy', 'Edited from the admin.')
        ->set('data.priceLabel', 'Join · $30/mo')
        ->call('save')
        ->assertHasNoErrors();

    $stored = app(SiteSettingsService::class)->get(SiteSettingsService::KEY_HOME_MEMBERSHIP);
    expect($stored['copy'])->toBe('Edited from the admin.')
        ->and($stored['priceLabel'])->toBe('Join · $30/mo')
        ->and($stored['perks'])->not->toBeEmpty();

    getJson('/api/site-content/home')
        ->assertJsonPath('data.membership.copy', 'Edited from the admin.');
});

test('the page reloads previously saved values, not the defaults', function (): void {
    $admin = $this->actingAsAdmin();
    app(SiteSettingsService::class)->set(
        SiteSettingsService::KEY_HOME_MEMBERSHIP,
        membershipBlob(['eyebrow' => 'Saved Eyebrow']),
        $admin,
    );

    Livewire::test(HomePageContent::class)
        ->assertSet('data.eyebrow', 'Saved Eyebrow');
});

test('saving requires the required fields', function (): void {
    $this->actingAsAdmin();

    Livewire::test(HomePageContent::class)
        ->set('data.copy', '')
        ->call('save')
        ->assertHasErrors(['data.copy']);
});
