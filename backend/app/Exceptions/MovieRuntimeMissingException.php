<?php

namespace App\Exceptions;

use App\Models\Movie;
use DomainException;

/**
 * Thrown when a showtime create/update would use a movie whose `runtime` is
 * NULL. Conflict math is undefined without a runtime, so the write is refused
 * before any DB work. The UI (ShowtimeResource) surfaces this as a form error
 * with a link to the movie edit page so staff can backfill runtime and retry.
 */
class MovieRuntimeMissingException extends DomainException
{
    public function __construct(public readonly Movie $movie)
    {
        parent::__construct(sprintf(
            'Cannot schedule "%s" — this movie has no runtime set. Edit the movie to add a runtime before scheduling.',
            $movie->title,
        ));
    }
}
