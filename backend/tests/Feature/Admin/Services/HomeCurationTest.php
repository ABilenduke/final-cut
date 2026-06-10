<?php

use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Resources\MovieResource\Pages\ListMovies;
use App\Models\Location;
use App\Models\MenuItem;
use App\Models\Movie;
use App\Services\MovieService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\getJson;

test('featureOnHome stamps the movie and clears any previously featured one', function (): void {
    $admin = $this->actingAsAdmin();
    $service = app(MovieService::class);

    $previous = Movie::factory()->create(['home_featured_at' => now()->subDay()]);
    $next = Movie::factory()->create();

    $service->featureOnHome($next, $admin);

    expect($next->refresh()->home_featured_at)->not->toBeNull()
        ->and($previous->refresh()->home_featured_at)->toBeNull();

    $activity = Activity::query()
        ->where('description', 'movie.featured_on_home')
        ->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe((string) $admin->id);
});

test('unfeatureFromHome clears the stamp and logs activity', function (): void {
    $admin = $this->actingAsAdmin();
    $movie = Movie::factory()->create(['home_featured_at' => now()]);

    app(MovieService::class)->unfeatureFromHome($movie, $admin);

    expect($movie->refresh()->home_featured_at)->toBeNull()
        ->and(Activity::query()->where('description', 'movie.unfeatured_from_home')->exists())
        ->toBeTrue();
});

test('the movies api exposes homeFeaturedAt', function (): void {
    Movie::factory()->create([
        'status' => 'now_showing',
        'home_featured_at' => now(),
    ]);

    $response = getJson('/api/movies?status=now_showing')->assertOk();

    expect($response->json('data.0.homeFeaturedAt'))->not->toBeNull();
});

test('the food menu api exposes featuredOnHomeAt', function (): void {
    $location = Location::factory()->create();
    MenuItem::factory()->forLocation($location)->create([
        'name' => 'Featured Popcorn',
        'featured_on_home_at' => now(),
    ]);
    MenuItem::factory()->forLocation($location)->create(['name' => 'Plain Drink']);

    $response = getJson('/api/food-menu')->assertOk();

    $byName = collect($response->json('data'))->keyBy('name');
    expect($byName['Featured Popcorn']['featuredOnHomeAt'])->not->toBeNull()
        ->and($byName['Plain Drink']['featuredOnHomeAt'])->toBeNull();
});

test('the movie table action features through the service and enforces the single-feature invariant', function (): void {
    $this->actingAsAdmin();

    $previous = Movie::factory()->create(['home_featured_at' => now()->subDay()]);
    $movie = Movie::factory()->create();

    Livewire::test(ListMovies::class)
        ->callTableAction('feature_on_home', $movie);

    expect($movie->refresh()->home_featured_at)->not->toBeNull()
        ->and($previous->refresh()->home_featured_at)->toBeNull();

    Livewire::test(ListMovies::class)
        ->callTableAction('unfeature_from_home', $movie);

    expect($movie->refresh()->home_featured_at)->toBeNull();
});

test('ops cannot see the movie feature action', function (): void {
    $this->actingAsOps();
    $movie = Movie::factory()->create();

    Livewire::test(ListMovies::class)
        ->assertTableActionHidden('feature_on_home', $movie);
});

test('the menu item toggle flips featured_on_home_at both ways', function (): void {
    $this->actingAsAdmin();
    $item = MenuItem::factory()->create();

    Livewire::test(ListMenuItems::class)
        ->callTableAction('feature_on_home', $item);
    expect($item->refresh()->featured_on_home_at)->not->toBeNull();

    Livewire::test(ListMenuItems::class)
        ->callTableAction('unfeature_from_home', $item);
    expect($item->refresh()->featured_on_home_at)->toBeNull();
});

test('ops cannot see the menu item feature action', function (): void {
    $this->actingAsOps();
    $item = MenuItem::factory()->create();

    Livewire::test(ListMenuItems::class)
        ->assertTableActionHidden('feature_on_home', $item);
});
