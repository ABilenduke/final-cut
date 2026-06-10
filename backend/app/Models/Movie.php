<?php

namespace App\Models;

use App\Enums\MovieStatus;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tmdb_id
 * @property string $slug
 * @property string $title
 * @property string|null $tagline
 * @property string|null $synopsis
 * @property int|null $runtime
 * @property float|null $rating
 * @property Carbon|null $release_date
 * @property array|null $genres
 * @property array|null $cast
 * @property string|null $poster_url
 * @property string|null $backdrop_url
 * @property string|null $trailer_key
 * @property MovieStatus $status
 * @property Carbon|null $tmdb_enriched_at
 * @property Carbon|null $home_featured_at Home hero curation flag — at most one movie carries it (MovieService::featureOnHome).
 */
#[Fillable([
    'tmdb_id', 'slug', 'title', 'tagline', 'synopsis', 'runtime',
    'rating', 'release_date', 'genres', 'cast', 'poster_url', 'backdrop_url',
    'trailer_key', 'status', 'tmdb_enriched_at', 'home_featured_at',
])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'genres' => 'array',
            'cast' => 'array',
            'release_date' => 'date',
            'status' => MovieStatus::class,
            'rating' => 'float',
            'runtime' => 'integer',
            'tmdb_enriched_at' => 'datetime',
            'home_featured_at' => 'datetime',
        ];
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
}
