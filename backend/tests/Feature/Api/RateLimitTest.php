<?php

use App\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Auth & lookup rate limiting (ultrareview P0 #1)
|--------------------------------------------------------------------------
| The customer auth endpoints previously had no dedicated throttle (the nginx
| zone regex missed the /api/auth/ prefix and no Laravel limiter applied), so
| only the generic 60/min API throttle stood between an attacker and unlimited
| credential-stuffing. These lock in the app-layer `auth` (5/min per ip+email)
| and `public-lookup` (30/min per ip) limiters.
*/

test('login is throttled to 5 attempts per minute per email', function () {
    User::factory()->create([
        'email' => 'victim@finalcut.test',
        'password' => 'password123',
    ]);

    // 5 wrong-password attempts are allowed (each 401)…
    foreach (range(1, 5) as $i) {
        postJson('/api/auth/login', [
            'email' => 'victim@finalcut.test',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    // …the 6th is rate-limited.
    postJson('/api/auth/login', [
        'email' => 'victim@finalcut.test',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('login throttle is scoped per email — a different account is unaffected', function () {
    User::factory()->create(['email' => 'a@finalcut.test', 'password' => 'password123']);
    User::factory()->create(['email' => 'b@finalcut.test', 'password' => 'password123']);

    foreach (range(1, 5) as $i) {
        postJson('/api/auth/login', ['email' => 'a@finalcut.test', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    // Different email key → still allowed.
    postJson('/api/auth/login', ['email' => 'b@finalcut.test', 'password' => 'wrong'])
        ->assertStatus(401);
});

test('forgot-password is throttled by the auth limiter', function () {
    foreach (range(1, 5) as $i) {
        postJson('/api/auth/forgot-password', ['email' => 'someone@finalcut.test'])
            ->assertOk();
    }

    postJson('/api/auth/forgot-password', ['email' => 'someone@finalcut.test'])
        ->assertStatus(429);
});

test('gift-card balance lookup is throttled to 30 per minute', function () {
    foreach (range(1, 30) as $i) {
        getJson('/api/gift-cards/balance?code=GC-NOPE'.$i)->assertStatus(404);
    }

    getJson('/api/gift-cards/balance?code=GC-NOPE-OVER')->assertStatus(429);
});
