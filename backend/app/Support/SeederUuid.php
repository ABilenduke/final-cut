<?php

declare(strict_types=1);

namespace App\Support;

use Ramsey\Uuid\Uuid;

/**
 * Deterministic UUIDv5 generator for database seeders.
 *
 * Seeders historically used random Str::uuid() / HasUuids on every run, so
 * `make fresh` rotated every PK and invalidated Nuxt ISR payloads and any
 * open browser tab pointing at /purchase/{showtimeId}. Routing every seeded
 * row's `id` through `SeederUuid::for($naturalKey)` makes the IDs reproducible
 * across reseeds — cached payloads stay valid.
 *
 * Natural-key convention: `"{entity}:{location_slug}:{...stable_attrs}"`,
 * e.g. `"showtime:eastside:Screen 1:fight-club:2026-05-19T19:30:00+00:00"`.
 */
final class SeederUuid
{
    private const NAMESPACE = '5a3e8c8a-9c5f-4f1d-9e2a-3d0c6b8f1a2e';

    public static function for(string $natural): string
    {
        return Uuid::uuid5(self::NAMESPACE, $natural)->toString();
    }
}
