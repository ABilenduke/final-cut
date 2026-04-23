<?php

namespace App\Jobs;

use App\Models\Movie;
use App\Services\MovieService;
use App\Services\TmdbService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class EnrichMovieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $movieId) {}

    public function handle(TmdbService $tmdb): void
    {
        try {
            $movie = Movie::find($this->movieId);

            if ($movie !== null) {
                $tmdb->enrichMovie($movie);
            }
        } finally {
            Cache::lock(MovieService::enrichmentLockKey($this->movieId))->forceRelease();
        }
    }
}
