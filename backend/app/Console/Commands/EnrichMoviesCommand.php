<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Services\TmdbService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class EnrichMoviesCommand extends Command
{
    protected $signature = 'movies:enrich
        {--movie= : Enrich a specific movie by slug}
        {--force : Re-enrich regardless of staleness}
        {--stale-hours=24 : Hours before data is considered stale}';

    protected $description = 'Enrich local movies with TMDB metadata (cast, images, trailer, etc.)';

    public function handle(TmdbService $tmdb): int
    {
        $movies = $this->getMoviesToEnrich();

        if ($movies === null) {
            $this->error("Movie not found: {$this->option('movie')}");

            return self::FAILURE;
        }

        if ($movies->isEmpty()) {
            $this->info('No movies need enrichment.');

            return self::SUCCESS;
        }

        $this->info("Enriching {$movies->count()} movie(s)...");

        $bar = $this->output->createProgressBar($movies->count());
        $bar->start();

        $succeeded = 0;
        $failed = 0;

        foreach ($movies as $movie) {
            if ($tmdb->enrichMovie($movie)) {
                $succeeded++;
                $this->line(" <info>✓</info> {$movie->title}");
            } else {
                $failed++;
                $this->line(" <error>✗</error> {$movie->title} (TMDB unavailable or no tmdb_id)");
            }

            $bar->advance();

            // Respect TMDB rate limits (~40 req/10s)
            usleep(200_000);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done. Succeeded: {$succeeded}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Movie>|null
     */
    private function getMoviesToEnrich(): ?Collection
    {
        if ($slug = $this->option('movie')) {
            $movie = Movie::where('slug', $slug)->first();

            if (! $movie) {
                return null;
            }

            return new Collection([$movie]);
        }

        $query = Movie::whereNotNull('tmdb_id');

        if (! $this->option('force')) {
            $staleHours = (int) $this->option('stale-hours');

            $query->where(function ($q) use ($staleHours) {
                $q->whereNull('tmdb_enriched_at')
                    ->orWhere('tmdb_enriched_at', '<', now()->subHours($staleHours));
            });
        }

        return $query->get();
    }
}
