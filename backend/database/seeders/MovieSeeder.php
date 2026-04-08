<?php

namespace Database\Seeders;

use App\Enums\MovieStatus;
use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $useLiveTmdb = (bool) config('seeders.movies.live_tmdb', app()->environment('local'));

        $nowShowing = [
            ['tmdb_id' => 550, 'title' => 'Fight Club', 'runtime' => 139, 'rating' => 8.4, 'genres' => [['id' => 18, 'name' => 'Drama']]],
            ['tmdb_id' => 278, 'title' => 'The Shawshank Redemption', 'runtime' => 142, 'rating' => 8.7, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 80, 'name' => 'Crime']]],
            ['tmdb_id' => 680, 'title' => 'Pulp Fiction', 'runtime' => 154, 'rating' => 8.5, 'genres' => [['id' => 53, 'name' => 'Thriller'], ['id' => 80, 'name' => 'Crime']]],
            ['tmdb_id' => 13, 'title' => 'Forrest Gump', 'runtime' => 142, 'rating' => 8.5, 'genres' => [['id' => 35, 'name' => 'Comedy'], ['id' => 18, 'name' => 'Drama']]],
            ['tmdb_id' => 155, 'title' => 'The Dark Knight', 'runtime' => 152, 'rating' => 8.5, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 28, 'name' => 'Action']]],
            ['tmdb_id' => 603, 'title' => 'The Matrix', 'runtime' => 136, 'rating' => 8.2, 'genres' => [['id' => 878, 'name' => 'Science Fiction'], ['id' => 28, 'name' => 'Action']]],
            ['tmdb_id' => 27205, 'title' => 'Inception', 'runtime' => 148, 'rating' => 8.4, 'genres' => [['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']]],
            ['tmdb_id' => 157336, 'title' => 'Interstellar', 'runtime' => 169, 'rating' => 8.4, 'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 18, 'name' => 'Drama']]],
            ['tmdb_id' => 238, 'title' => 'The Godfather', 'runtime' => 175, 'rating' => 8.7, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 80, 'name' => 'Crime']]],
            ['tmdb_id' => 424, 'title' => 'Schindler\'s List', 'runtime' => 195, 'rating' => 8.6, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 36, 'name' => 'History']]],
            ['tmdb_id' => 497, 'title' => 'The Green Mile', 'runtime' => 189, 'rating' => 8.5, 'genres' => [['id' => 18, 'name' => 'Drama'], ['id' => 80, 'name' => 'Crime']]],
            ['tmdb_id' => 299536, 'title' => 'Avengers: Infinity War', 'runtime' => 149, 'rating' => 8.2, 'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 28, 'name' => 'Action']]],
        ];

        $comingSoon = [
            ['tmdb_id' => 120, 'title' => 'The Lord of the Rings: The Fellowship of the Ring', 'runtime' => 178, 'rating' => 0, 'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 14, 'name' => 'Fantasy']]],
            ['tmdb_id' => 121, 'title' => 'The Lord of the Rings: The Two Towers', 'runtime' => 179, 'rating' => 0, 'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 14, 'name' => 'Fantasy']]],
            ['tmdb_id' => 122, 'title' => 'The Lord of the Rings: The Return of the King', 'runtime' => 201, 'rating' => 0, 'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 14, 'name' => 'Fantasy']]],
            ['tmdb_id' => 329, 'title' => 'Jurassic Park', 'runtime' => 127, 'rating' => 0, 'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 878, 'name' => 'Science Fiction']]],
            ['tmdb_id' => 862, 'title' => 'Toy Story', 'runtime' => 81, 'rating' => 0, 'genres' => [['id' => 16, 'name' => 'Animation'], ['id' => 10751, 'name' => 'Family']]],
            ['tmdb_id' => 634649, 'title' => 'Spider-Man: No Way Home', 'runtime' => 148, 'rating' => 0, 'genres' => [['id' => 28, 'name' => 'Action'], ['id' => 12, 'name' => 'Adventure']]],
            ['tmdb_id' => 667538, 'title' => 'Transformers: Rise of the Beasts', 'runtime' => 127, 'rating' => 0, 'genres' => [['id' => 878, 'name' => 'Science Fiction'], ['id' => 12, 'name' => 'Adventure']]],
            ['tmdb_id' => 299534, 'title' => 'Avengers: Endgame', 'runtime' => 181, 'rating' => 0, 'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 878, 'name' => 'Science Fiction']]],
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
                'tmdb_enriched_at' => $useLiveTmdb ? null : now(),
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
                'tmdb_enriched_at' => $useLiveTmdb ? null : now(),
            ]);
        }

        if ($useLiveTmdb) {
            $this->enrichWithLiveTmdb();
        }
    }

    private function enrichWithLiveTmdb(): void
    {
        if (blank(config('services.tmdb.api_key'))) {
            throw new RuntimeException('TMDB_API_KEY is required for local movie seeding with live TMDB enrichment.');
        }

        $exitCode = Artisan::call('movies:enrich', ['--force' => true]);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());
            throw new RuntimeException(
                "Local movie seeding failed: live TMDB enrichment did not complete successfully.\n\n{$output}"
            );
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
