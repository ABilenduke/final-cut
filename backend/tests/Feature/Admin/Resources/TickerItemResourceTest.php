<?php

use App\Filament\Resources\TickerItemResource;
use App\Filament\Resources\TickerItemResource\Pages\CreateTickerItem;
use App\Filament\Resources\TickerItemResource\Pages\ListTickerItems;
use App\Models\TickerItem;
use Database\Seeders\TickerItemSeeder;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

// ── Public API ──────────────────────────────────────────────────────────────

test('the api returns only active in-window items in display order', function (): void {
    TickerItem::factory()->create([
        'label' => 'Event', 'text' => 'Live two', 'display_order' => 2,
        'published_at' => now()->subDay(),
    ]);
    TickerItem::factory()->create([
        'label' => 'Now Showing', 'text' => 'Live one', 'href' => '/movies/dune', 'display_order' => 1,
        'published_at' => now()->subDay(),
    ]);
    TickerItem::factory()->create(['text' => 'Draft', 'published_at' => null]);
    TickerItem::factory()->create([
        'text' => 'Expired', 'published_at' => now()->subWeek(), 'ends_at' => now()->subDay(),
    ]);
    TickerItem::factory()->create([
        'text' => 'Future window', 'published_at' => now()->subDay(), 'starts_at' => now()->addDay(),
    ]);

    getJson('/api/ticker-items')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.text', 'Live one')
        ->assertJsonPath('data.0.label', 'Now Showing')
        ->assertJsonPath('data.0.href', '/movies/dune')
        ->assertJsonPath('data.1.text', 'Live two')
        ->assertJsonPath('data.1.href', null);
});

test('an empty result is valid and the cache busts on save', function (): void {
    getJson('/api/ticker-items')->assertOk()->assertJsonCount(0, 'data');

    $item = TickerItem::factory()->create([
        'text' => 'Fresh', 'published_at' => now()->subMinute(),
    ]);
    getJson('/api/ticker-items')->assertOk()->assertJsonPath('data.0.text', 'Fresh');

    $item->update(['text' => 'Edited']);
    getJson('/api/ticker-items')->assertOk()->assertJsonPath('data.0.text', 'Edited');
});

// ── Admin resource ──────────────────────────────────────────────────────────

test('a manager can create items and publish them from the list', function (): void {
    $this->actingAsManager();

    Livewire::test(CreateTickerItem::class)
        ->set('data.label', 'Members')
        ->set('data.text', 'Bar opens 60 min before all screenings')
        ->set('data.display_order', 3)
        ->call('create')
        ->assertHasNoFormErrors();

    $item = TickerItem::where('label', 'Members')->first();
    expect($item)->not->toBeNull()
        ->and($item->published_at)->toBeNull()
        ->and($item->displayStatus())->toBe('draft');

    Livewire::test(ListTickerItems::class)
        ->callTableAction('publish', $item)
        ->assertHasNoTableActionErrors();

    expect($item->refresh()->displayStatus())->toBe('live');
});

test('ops cannot access the resource', function (): void {
    $this->actingAsOps();
    expect(TickerItemResource::canViewAny())->toBeFalse();

    $this->actingAsManager();
    expect(TickerItemResource::canViewAny())->toBeTrue();
});

// ── Seeder parity ───────────────────────────────────────────────────────────

test('the seeder imports the nine legacy hardcoded items as live', function (): void {
    $this->seed(TickerItemSeeder::class);

    expect(TickerItem::count())->toBe(9);

    getJson('/api/ticker-items')->assertOk()->assertJsonCount(9, 'data');
});
