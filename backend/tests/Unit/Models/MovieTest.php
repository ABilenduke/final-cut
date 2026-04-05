<?php

use App\Enums\MovieStatus;
use App\Models\Movie;

it('creates a movie with auto-increment primary key', function () {
    $movie = Movie::factory()->create();
    expect($movie->id)->toBeInt();
    expect($movie->incrementing)->toBeTrue();
});

it('enforces unique slug constraint', function () {
    Movie::factory()->create(['slug' => 'test-movie']);
    Movie::factory()->create(['slug' => 'test-movie']);
})->throws(\Illuminate\Database\QueryException::class);

it('enforces unique tmdb_id constraint', function () {
    Movie::factory()->create(['tmdb_id' => 12345]);
    Movie::factory()->create(['tmdb_id' => 12345]);
})->throws(\Illuminate\Database\QueryException::class);

it('allows null tmdb_id', function () {
    $movie = Movie::factory()->create(['tmdb_id' => null]);
    expect($movie->tmdb_id)->toBeNull();
});

it('casts genres to array', function () {
    $movie = Movie::factory()->create([
        'genres' => [['id' => 28, 'name' => 'Action']],
    ]);
    $movie->refresh();
    expect($movie->genres)->toBeArray();
    expect($movie->genres[0]['name'])->toBe('Action');
});

it('casts cast to array', function () {
    $movie = Movie::factory()->create([
        'cast' => [['id' => 1, 'name' => 'Actor 1', 'character' => 'Hero', 'profileUrl' => null]],
    ]);
    $movie->refresh();
    expect($movie->cast)->toBeArray();
    expect($movie->cast[0]['name'])->toBe('Actor 1');
});

it('casts status to MovieStatus enum', function () {
    $movie = Movie::factory()->create(['status' => 'now_showing']);
    expect($movie->status)->toBe(MovieStatus::NowShowing);
});

it('casts release_date to date', function () {
    $movie = Movie::factory()->create(['release_date' => '2026-06-15']);
    expect($movie->release_date)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('casts tmdb_enriched_at to datetime', function () {
    $movie = Movie::factory()->create(['tmdb_enriched_at' => '2026-04-04 12:00:00']);
    expect($movie->tmdb_enriched_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('defines showtimes relationship method', function () {
    $movie = Movie::factory()->make();
    expect(method_exists($movie, 'showtimes'))->toBeTrue();
});
