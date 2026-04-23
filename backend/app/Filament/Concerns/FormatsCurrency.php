<?php

namespace App\Filament\Concerns;

trait FormatsCurrency
{
    public static function centsToDisplay(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        return '$'.number_format($cents / 100, 2);
    }

    public static function displayToCents(?string $display): ?int
    {
        if ($display === null) {
            return null;
        }

        $trimmed = trim($display);

        if ($trimmed === '') {
            return null;
        }

        $isNegative = str_starts_with($trimmed, '-');
        $numeric = preg_replace('/[^\d.]/', '', $trimmed);

        // Reject input with no digits (e.g. 'abc', '$', '-') — silently coercing
        // garbage to 0 cents hides bad form input. Empty/whitespace-only is
        // handled above; this guards text that survives the strip with no digits.
        if ($numeric === null || ! preg_match('/\d/', $numeric)) {
            return null;
        }

        $cents = (int) round(((float) $numeric) * 100);

        return $isNegative ? -$cents : $cents;
    }
}
