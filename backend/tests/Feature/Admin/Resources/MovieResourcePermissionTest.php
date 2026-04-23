<?php

use App\Filament\Resources\MovieResource;
use App\Filament\Resources\MovieResource\Pages\CreateMovie;
use App\Filament\Resources\MovieResource\Pages\ListMovies;
use App\Models\Movie;
use Livewire\Livewire;

test('ops can view the movie list but cannot create, edit, delete, enrich, or use bulk status actions', function (): void {
    $this->actingAsOps();
    $movie = Movie::factory()->create(['tmdb_id' => 12345]);

    expect(MovieResource::canViewAny())->toBeTrue();
    expect(MovieResource::canCreate())->toBeFalse();
    expect(MovieResource::canEdit($movie))->toBeFalse();
    expect(MovieResource::canDelete($movie))->toBeFalse();

    $this->get(CreateMovie::getUrl())->assertForbidden();

    Livewire::test(ListMovies::class)
        ->assertTableActionHidden('enrich', $movie)
        ->assertTableBulkActionHidden('mark_now_showing')
        ->assertTableBulkActionHidden('mark_coming_soon');
});

test('managers can create, edit, delete, enrich, and use bulk status actions', function (): void {
    $this->actingAsManager();
    $movie = Movie::factory()->create(['tmdb_id' => 12345]);

    expect(MovieResource::canViewAny())->toBeTrue();
    expect(MovieResource::canCreate())->toBeTrue();
    expect(MovieResource::canEdit($movie))->toBeTrue();
    expect(MovieResource::canDelete($movie))->toBeTrue();

    Livewire::test(ListMovies::class)
        ->assertTableActionVisible('enrich', $movie)
        ->assertTableBulkActionVisible('mark_now_showing')
        ->assertTableBulkActionVisible('mark_coming_soon');
});

test('an admin user with no role cannot view the movie list', function (): void {
    $this->actingAsNobody();

    expect(MovieResource::canViewAny())->toBeFalse();

    $this->get(ListMovies::getUrl())->assertForbidden();
});
