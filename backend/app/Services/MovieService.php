<?php

namespace App\Services;

use App\Exceptions\MovieHasBookingsException;
use App\Jobs\EnrichMovieJob;
use App\Models\Movie;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MovieService
{
    use LogsAdminActivity;

    /**
     * @param  array{
     *   title: string, slug: string, status: 'now_showing'|'coming_soon',
     *   tmdb_id?: ?int, tagline?: ?string, synopsis?: ?string,
     *   runtime?: ?int, rating?: ?float, release_date?: ?string,
     *   poster_url?: ?string, backdrop_url?: ?string, trailer_key?: ?string,
     *   genres?: array<int, array{id: int, name: string}>,
     *   cast?: array<int, array{id?: int, name: string, character: string, profileUrl?: ?string}>
     * }  $attributes
     */
    public function create(array $attributes, ?User $actor = null): Movie
    {
        $movie = Movie::create($attributes);

        $this->logIfAdmin('movie.created', $movie, $actor, $attributes);
        $this->forgetGenreFilterCache();

        return $movie;
    }

    public function update(Movie $movie, array $attributes, ?User $actor = null): Movie
    {
        $movie->fill($attributes);
        $dirtyKeys = array_keys($movie->getDirty());
        $before = array_intersect_key($movie->getOriginal(), array_flip($dirtyKeys));
        $movie->save();
        $after = array_intersect_key($movie->getAttributes(), array_flip($dirtyKeys));

        if ($dirtyKeys !== []) {
            $this->logIfAdmin('movie.updated', $movie, $actor, [
                'before' => $before,
                'after' => $after,
            ]);

            if (array_key_exists('genres', $before) || array_key_exists('genres', $after)) {
                $this->forgetGenreFilterCache();
            }
        }

        return $movie;
    }

    /**
     * @throws MovieHasBookingsException if any of the movie's showtimes has a booking.
     *                                   Admins must resolve (cancel/refund) those bookings before the movie can
     *                                   be deleted; the FK cascade would otherwise silently destroy the payment
     *                                   intent IDs needed to issue refunds.
     */
    public function delete(Movie $movie, ?User $actor = null): void
    {
        if ($movie->showtimes()->whereHas('bookings')->exists()) {
            throw new MovieHasBookingsException;
        }

        $this->logIfAdmin('movie.deleted', $movie, $actor);
        $movie->delete();
        $this->forgetGenreFilterCache();
    }

    public function triggerEnrichment(Movie $movie, ?User $actor = null): bool
    {
        if (! $movie->tmdb_id) {
            return false;
        }

        $lock = Cache::lock(self::enrichmentLockKey($movie->id), 300);

        if (! $lock->get()) {
            return false;
        }

        try {
            EnrichMovieJob::dispatch($movie->id, $lock->owner());
        } catch (Throwable $e) {
            // Job never reached the queue; release the lock so the next trigger
            // isn't lied to for the full 5-minute TTL.
            $lock->forceRelease();
            throw $e;
        }

        $this->logIfAdmin('movie.enrichment_triggered', $movie, $actor);

        return true;
    }

    public static function enrichmentLockKey(int $movieId): string
    {
        return "movie:enrich:{$movieId}";
    }

    public const GENRE_FILTER_CACHE_KEY = 'movies:genre_filter_options';

    private function forgetGenreFilterCache(): void
    {
        Cache::forget(self::GENRE_FILTER_CACHE_KEY);
    }
}
