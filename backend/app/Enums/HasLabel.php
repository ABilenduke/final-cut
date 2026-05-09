<?php

namespace App\Enums;

/**
 * Backed enums whose values surface in the UI implement this so generic
 * formatters (Filament resources, API resources) can render the
 * human-readable label without depending on a concrete enum type.
 */
interface HasLabel
{
    public function label(): string;
}
