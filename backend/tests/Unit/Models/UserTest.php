<?php

use App\Enums\LoyaltyTier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

it('creates a user with UUID primary key', function () {
    $user = User::factory()->create();
    expect($user->id)->toBeString();
    expect(Str::isUuid($user->id))->toBeTrue();
});

it('has correct default loyalty values', function () {
    $user = User::factory()->create();
    expect($user->loyalty_points)->toBe(0);
    expect($user->loyalty_tier)->toBe(LoyaltyTier::Member);
});

it('casts loyalty_tier to LoyaltyTier enum', function () {
    $user = User::factory()->create(['loyalty_tier' => 'premier']);
    expect($user->loyalty_tier)->toBe(LoyaltyTier::Premier);
});

it('casts date_of_birth to date', function () {
    $user = User::factory()->create(['date_of_birth' => '1990-05-15']);
    expect($user->date_of_birth)->toBeInstanceOf(Carbon::class);
});

it('has bookings relationship', function () {
    $user = User::factory()->create();
    expect($user->bookings())->toBeInstanceOf(HasMany::class);
});
