<?php

namespace Database\Seeders;

use App\Enums\MovieStatus;
use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $nowShowing = [
            ['tmdb_id' => 100001, 'title' => 'The Last Frontier', 'runtime' => 142, 'rating' => 8.2, 'genres' => [['id' => 878, 'name' => 'Science Fiction'], ['id' => 28, 'name' => 'Action']]],
            ['tmdb_id' => 100002, 'title' => 'Midnight in Venice', 'runtime' => 118, 'rating' => 7.8, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 10749, 'name' => 'Romance']]],
            ['tmdb_id' => 100003, 'title' => 'Shadow Protocol', 'runtime' => 131, 'rating' => 7.5, 'genres' => [['id' => 53, 'name' => 'Thriller'], ['id' => 28, 'name' => 'Action']]],
            ['tmdb_id' => 100004, 'title' => 'The Cartographer\'s Daughter', 'runtime' => 156, 'rating' => 8.6, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 36, 'name' => 'History']]],
            ['tmdb_id' => 100005, 'title' => 'Neon Requiem', 'runtime' => 98, 'rating' => 7.1, 'genres' => [['id' => 878, 'name' => 'Science Fiction'], ['id' => 53, 'name' => 'Thriller']]],
            ['tmdb_id' => 100006, 'title' => 'Wildflower', 'runtime' => 105, 'rating' => 7.9, 'genres' => [['id' => 18, 'name' => 'Drama']]],
            ['tmdb_id' => 100007, 'title' => 'The Deep Below', 'runtime' => 112, 'rating' => 6.8, 'genres' => [['id' => 27, 'name' => 'Horror'], ['id' => 53, 'name' => 'Thriller']]],
            ['tmdb_id' => 100008, 'title' => 'Paper Airplanes', 'runtime' => 94, 'rating' => 8.0, 'genres' => [['id' => 16, 'name' => 'Animation'], ['id' => 10751, 'name' => 'Family']]],
            ['tmdb_id' => 100009, 'title' => 'Ironclad', 'runtime' => 148, 'rating' => 7.4, 'genres' => [['id' => 28, 'name' => 'Action'], ['id' => 12, 'name' => 'Adventure']]],
            ['tmdb_id' => 100010, 'title' => 'The Verdict', 'runtime' => 128, 'rating' => 8.3, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 80, 'name' => 'Crime']]],
            ['tmdb_id' => 100011, 'title' => 'Laugh Track', 'runtime' => 102, 'rating' => 7.0, 'genres' => [['id' => 35, 'name' => 'Comedy']]],
            ['tmdb_id' => 100012, 'title' => 'Starfall', 'runtime' => 165, 'rating' => 8.8, 'genres' => [['id' => 878, 'name' => 'Science Fiction'], ['id' => 12, 'name' => 'Adventure']]],
        ];

        $comingSoon = [
            ['tmdb_id' => 100013, 'title' => 'The Alchemist\'s Journal', 'runtime' => 138, 'rating' => 0, 'genres' => [['id' => 14, 'name' => 'Fantasy'], ['id' => 12, 'name' => 'Adventure']]],
            ['tmdb_id' => 100014, 'title' => 'Glass Houses', 'runtime' => 115, 'rating' => 0, 'genres' => [['id' => 53, 'name' => 'Thriller'], ['id' => 9648, 'name' => 'Mystery']]],
            ['tmdb_id' => 100015, 'title' => 'Boundless', 'runtime' => 122, 'rating' => 0, 'genres' => [['id' => 99, 'name' => 'Documentary']]],
            ['tmdb_id' => 100016, 'title' => 'The Last Dance', 'runtime' => 108, 'rating' => 0, 'genres' => [['id' => 10402, 'name' => 'Music'], ['id' => 18, 'name' => 'Drama']]],
            ['tmdb_id' => 100017, 'title' => 'Phantom Signal', 'runtime' => 145, 'rating' => 0, 'genres' => [['id' => 878, 'name' => 'Science Fiction']]],
            ['tmdb_id' => 100018, 'title' => 'Roots', 'runtime' => 130, 'rating' => 0, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 10751, 'name' => 'Family']]],
            ['tmdb_id' => 100019, 'title' => 'Crimson Peak Redux', 'runtime' => 140, 'rating' => 0, 'genres' => [['id' => 27, 'name' => 'Horror'], ['id' => 14, 'name' => 'Fantasy']]],
            ['tmdb_id' => 100020, 'title' => 'First Light', 'runtime' => 96, 'rating' => 0, 'genres' => [['id' => 16, 'name' => 'Animation'], ['id' => 878, 'name' => 'Science Fiction']]],
        ];

        foreach ($nowShowing as $movie) {
            Movie::create([
                'tmdb_id' => $movie['tmdb_id'],
                'slug' => Str::slug($movie['title']),
                'title' => $movie['title'],
                'tagline' => fake()->sentence(),
                'synopsis' => fake()->paragraphs(2, true),
                'runtime' => $movie['runtime'],
                'rating' => $movie['rating'],
                'release_date' => fake()->dateTimeBetween('-2 months', 'now'),
                'genres' => $movie['genres'],
                'cast' => $this->fakeCast(),
                'poster_url' => 'https://image.tmdb.org/t/p/w500/placeholder.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/placeholder.jpg',
                'trailer_key' => fake()->regexify('[a-zA-Z0-9]{11}'),
                'status' => MovieStatus::NowShowing,
                'tmdb_enriched_at' => now(),
            ]);
        }

        foreach ($comingSoon as $movie) {
            Movie::create([
                'tmdb_id' => $movie['tmdb_id'],
                'slug' => Str::slug($movie['title']),
                'title' => $movie['title'],
                'tagline' => fake()->sentence(),
                'synopsis' => fake()->paragraphs(2, true),
                'runtime' => $movie['runtime'],
                'rating' => $movie['rating'] ?: null,
                'release_date' => fake()->dateTimeBetween('+1 month', '+6 months'),
                'genres' => $movie['genres'],
                'cast' => $this->fakeCast(),
                'poster_url' => 'https://image.tmdb.org/t/p/w500/placeholder.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/placeholder.jpg',
                'trailer_key' => fake()->optional(0.5)->regexify('[a-zA-Z0-9]{11}'),
                'status' => MovieStatus::ComingSoon,
                'tmdb_enriched_at' => now(),
            ]);
        }
    }

    private function fakeCast(): array
    {
        return array_map(fn (int $i) => [
            'id' => $i,
            'name' => fake()->name(),
            'character' => fake()->firstName().' '.fake()->lastName(),
            'profileUrl' => 'https://image.tmdb.org/t/p/w185/placeholder.jpg',
        ], range(1, 5));
    }
}
