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
        if ($display === null || $display === '') {
            return null;
        }

        $numeric = preg_replace('/[^\d.]/', '', $display);

        return (int) round(((float) $numeric) * 100);
    }
}
