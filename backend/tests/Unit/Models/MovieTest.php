<?php

use App\Enums\MovieStatus;
use App\Models\Movie;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a movie with TMDB integer primary key', function () {
    $movie = Movie::factory()->create(['id' => 12345]);
    expect($movie->id)->toBe(12345);
    expect($movie->incrementing)->toBeFalse();
});

it('enforces unique slug constraint', function () {
    Movie::factory()->create(['slug' => 'test-movie']);
    Movie::factory()->create(['slug' => 'test-movie']);
})->throws(\Illuminate\Database\QueryException::class);

it('casts genres to array', function () {
    $movie = Movie::factory()->create([
        'genres' => [['id' => 28, 'name' => 'Action']],
    ]);
    $movie->refresh();
    expect($movie->genres)->toBeArray();
    expect($movie->genres[0]['name'])->toBe('Action');
});

it('casts status to MovieStatus enum', function () {
    $movie = Movie::factory()->create(['status' => 'now_showing']);
    expect($movie->status)->toBe(MovieStatus::NowShowing);
});

it('casts release_date to date', function () {
    $movie = Movie::factory()->create(['release_date' => '2026-06-15']);
    expect($movie->release_date)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('defines showtimes relationship method', function () {
    $movie = Movie::factory()->make();
    expect(method_exists($movie, 'showtimes'))->toBeTrue();
});
