<?php

use App\Enums\LoyaltyAdjustmentType;
use App\Models\LoyaltyAdjustment;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('creating with a valid enum change_type persists and casts back to the enum', function (): void {
    $admin = $this->actingAsAdmin();

    $adjustment = LoyaltyAdjustment::factory()
        ->for($admin, 'adminUser')
        ->create([
            'change_type' => LoyaltyAdjustmentType::PointsCorrection,
            'points_delta' => 100,
            'reason' => 'Manual correction',
        ]);

    $reloaded = $adjustment->fresh();

    expect($reloaded->change_type)->toBe(LoyaltyAdjustmentType::PointsCorrection);
    expect($reloaded->points_delta)->toBe(100);
    expect($reloaded->reason)->toBe('Manual correction');
});

test('creating with an unknown string change_type raises at the cast boundary', function (): void {
    $this->actingAsAdmin();

    expect(fn () => LoyaltyAdjustment::factory()->create(['change_type' => 'unknown_type']))
        ->toThrow(ValueError::class);
});

test('creating a LoyaltyAdjustment writes an activity row attributed to the admin causer', function (): void {
    $admin = $this->actingAsAdmin();
    Activity::query()->delete();

    $adjustment = LoyaltyAdjustment::factory()
        ->for($admin, 'adminUser')
        ->create();

    $activities = Activity::where('subject_type', LoyaltyAdjustment::class)
        ->where('subject_id', $adjustment->id)
        ->get();

    expect($activities)->toHaveCount(1);
    expect($activities->first()->description)->toBe('created');
    expect($activities->first()->causer_type)->toBe(User::class);
    expect($activities->first()->causer_id)->toBe($admin->id);
});

test('updating a LoyaltyAdjustment writes a second activity row with a diff limited to logOnly columns', function (): void {
    $admin = $this->actingAsAdmin();

    $adjustment = LoyaltyAdjustment::factory()
        ->for($admin, 'adminUser')
        ->create(['points_delta' => 100]);

    Activity::query()->delete();

    $adjustment->update(['points_delta' => 250]);

    $activity = Activity::where('subject_type', LoyaltyAdjustment::class)
        ->where('subject_id', $adjustment->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toBe('updated');

    $changes = $activity->properties->toArray();
    $touched = array_merge(
        array_keys($changes['attributes'] ?? []),
        array_keys($changes['old'] ?? []),
    );

    $allowed = ['user_id', 'admin_user_id', 'points_delta', 'change_type'];
    foreach ($touched as $key) {
        expect($allowed)->toContain($key);
    }
});

test('deleting the customer user preserves the adjustment audit row with a null user_id', function (): void {
    // P1 cascade-cluster fix: deleting a user must NOT destroy the loyalty
    // points audit trail — only the subject reference is cleared (nullOnDelete).
    $this->actingAsAdmin();

    $user = User::factory()->create();
    $adjustment = LoyaltyAdjustment::factory()->for($user)->create([
        'points_delta' => 175,
    ]);

    $user->delete();

    $fresh = LoyaltyAdjustment::find($adjustment->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->user_id)->toBeNull()
        ->and($fresh->points_delta)->toBe(175);
});
