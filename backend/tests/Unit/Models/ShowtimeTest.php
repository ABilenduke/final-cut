<?php

use App\Models\Auditorium;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a showtime with UUID primary key', function () {
    $showtime = Showtime::factory()->create();
    expect($showtime->id)->toBeString();
    expect(Str::isUuid($showtime->id))->toBeTrue();
});

it('belongs to a movie', function () {
    $showtime = Showtime::factory()->create();
    expect($showtime->movie)->toBeInstanceOf(Movie::class);
});

it('belongs to an auditorium', function () {
    $showtime = Showtime::factory()->create();
    expect($showtime->auditorium)->toBeInstanceOf(Auditorium::class);
});

it('stores prices as integers (cents)', function () {
    $showtime = Showtime::factory()->create([
        'price_standard' => 1200,
        'price_premium' => 1800,
        'price_accessible' => 1000,
    ]);
    expect($showtime->price_standard)->toBe(1200);
    expect($showtime->price_premium)->toBe(1800);
    expect($showtime->price_accessible)->toBe(1000);
});

it('casts start_time and end_time to datetime', function () {
    $showtime = Showtime::factory()->create();
    expect($showtime->start_time)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($showtime->end_time)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('cascades delete from movie', function () {
    $movie = Movie::factory()->create();
    Showtime::factory()->create(['movie_id' => $movie->id]);
    $movie->delete();
    expect(Showtime::count())->toBe(0);
});

it('cascades delete from auditorium', function () {
    $auditorium = Auditorium::factory()->create();
    Showtime::factory()->create(['auditorium_id' => $auditorium->id]);
    $auditorium->delete();
    expect(Showtime::count())->toBe(0);
});
