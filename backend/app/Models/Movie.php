<?php

namespace App\Models;

use App\Enums\MovieStatus;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id', 'slug', 'title', 'tagline', 'synopsis', 'runtime',
    'rating', 'release_date', 'genres', 'poster_url', 'backdrop_url',
    'trailer_key', 'status',
])]
class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'release_date' => 'date',
            'status' => MovieStatus::class,
            'rating' => 'decimal:1',
            'runtime' => 'integer',
        ];
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
}
