<?php

use Illuminate\Support\Str;

it('creates a user with UUID primary key', function () {
    $user = \App\Models\User::factory()->create();
    expect($user->id)->toBeString();
    expect(Str::isUuid($user->id))->toBeTrue();
});

it('has correct default loyalty values', function () {
    $user = \App\Models\User::factory()->create();
    expect($user->loyalty_points)->toBe(0);
    expect($user->loyalty_tier)->toBe(\App\Enums\LoyaltyTier::Member);
});

it('casts loyalty_tier to LoyaltyTier enum', function () {
    $user = \App\Models\User::factory()->create(['loyalty_tier' => 'premier']);
    expect($user->loyalty_tier)->toBe(\App\Enums\LoyaltyTier::Premier);
});

it('casts date_of_birth to date', function () {
    $user = \App\Models\User::factory()->create(['date_of_birth' => '1990-05-15']);
    expect($user->date_of_birth)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('has bookings relationship', function () {
    $user = \App\Models\User::factory()->create();
    expect($user->bookings())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
