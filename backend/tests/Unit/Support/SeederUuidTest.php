<?php

use App\Support\SeederUuid;

test('SeederUuid::for is deterministic — same key yields the same UUID', function () {
    $key = 'showtime:eastside:Screen 1:fight-club:2026-05-19T19:30:00+00:00';

    expect(SeederUuid::for($key))->toBe(SeederUuid::for($key));
});

test('SeederUuid::for yields different UUIDs for different keys', function () {
    expect(SeederUuid::for('menu:Large Popcorn'))
        ->not->toBe(SeederUuid::for('menu:Small Popcorn'));
});

test('SeederUuid::for produces a valid v5 (name-based) UUID', function () {
    $uuid = SeederUuid::for('user:test@finalcut.test');

    // Canonical UUID shape with the version nibble pinned to 5 and the variant
    // nibble in the RFC 4122 range (8/9/a/b).
    expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});
