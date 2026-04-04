<?php

namespace Database\Factories;

use App\Enums\MovieStatus;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Movie> */
class MovieFactory extends Factory
{
    protected $model = Movie::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'tmdb_id' => fake()->unique()->numberBetween(1000, 999999),
            'slug' => Str::slug($title),
            'title' => $title,
            'tagline' => fake()->optional()->sentence(),
            'synopsis' => fake()->paragraphs(2, true),
            'runtime' => fake()->numberBetween(80, 180),
            'rating' => fake()->randomFloat(1, 1, 10),
            'release_date' => fake()->dateTimeBetween('-1 year', '+6 months'),
            'genres' => [
                ['id' => 28, 'name' => 'Action'],
                ['id' => 12, 'name' => 'Adventure'],
            ],
            'cast' => [
                ['id' => 1, 'name' => fake()->name(), 'character' => fake()->name(), 'profileUrl' => 'https://image.tmdb.org/t/p/w185/placeholder.jpg'],
                ['id' => 2, 'name' => fake()->name(), 'character' => fake()->name(), 'profileUrl' => 'https://image.tmdb.org/t/p/w185/placeholder.jpg'],
                ['id' => 3, 'name' => fake()->name(), 'character' => fake()->name(), 'profileUrl' => 'https://image.tmdb.org/t/p/w185/placeholder.jpg'],
            ],
            'poster_url' => 'https://image.tmdb.org/t/p/w500/example.jpg',
            'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/example.jpg',
            'trailer_key' => fake()->optional()->regexify('[a-zA-Z0-9]{11}'),
            'status' => fake()->randomElement(MovieStatus::cases()),
            'tmdb_enriched_at' => now(),
        ];
    }

    public function nowShowing(): static
    {
        return $this->state(fn () => ['status' => MovieStatus::NowShowing]);
    }

    public function comingSoon(): static
    {
        return $this->state(fn () => ['status' => MovieStatus::ComingSoon]);
    }
}
