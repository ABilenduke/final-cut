<?php

namespace App\Support;

final class SeatSectionPalette
{
    private const PALETTE = [
        'rgba(37, 99, 235, 0.85)',   // blue
        'rgba(220, 38, 38, 0.85)',   // red
        'rgba(202, 138, 4, 0.85)',   // amber
        'rgba(22, 163, 74, 0.85)',   // green
        'rgba(147, 51, 234, 0.85)',  // purple
        'rgba(234, 88, 12, 0.85)',   // orange
    ];

    public const UNKNOWN = 'rgba(107, 114, 128, 0.65)'; // gray-500 — no section assigned

    public static function colorFor(?string $sectionId): string
    {
        if ($sectionId === null) {
            return self::UNKNOWN;
        }

        return self::PALETTE[abs(crc32($sectionId)) % count(self::PALETTE)];
    }
}
