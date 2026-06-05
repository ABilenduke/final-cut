<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

/*
|--------------------------------------------------------------------------
| GET /api/account/profile
|--------------------------------------------------------------------------
*/

test('get profile returns 200 with full user profile data', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@finalcut.test',
        'phone' => '+15551234567',
        'date_of_birth' => '1990-06-15',
        'avatar_url' => 'https://example.com/avatar.jpg',
        'loyalty_points' => 250,
        'loyalty_tier' => 'premier',
        'premier_expiry' => '2027-01-01',
    ]);

    $response = actingAs($user)->getJson('/api/account/profile');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'dateOfBirth',
                'avatarUrl',
                'loyaltyPoints',
                'loyaltyTier',
                'premierExpiry',
                'createdAt',
            ],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane@finalcut.test')
        ->assertJsonPath('data.phone', '+15551234567')
        ->assertJsonPath('data.avatarUrl', 'https://example.com/avatar.jpg')
        ->assertJsonPath('data.loyaltyPoints', 250)
        ->assertJsonPath('data.loyaltyTier', 'premier');
});

test('get profile returns camelCase keys and ISO8601 dates', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1990-06-15',
        'premier_expiry' => '2027-01-01',
    ]);

    $response = actingAs($user)->getJson('/api/account/profile');

    $response->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveKeys(['dateOfBirth', 'premierExpiry', 'createdAt', 'loyaltyPoints', 'loyaltyTier', 'avatarUrl'])
        ->and($data['dateOfBirth'])->toContain('1990-06-15')
        ->and($data['premierExpiry'])->toContain('2027-01-01')
        ->and($data['createdAt'])->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

test('get profile does not expose password, remember_token, or stripe_customer_id', function () {
    $user = User::factory()->create([
        'stripe_customer_id' => 'cus_test_123',
    ]);

    $response = actingAs($user)->getJson('/api/account/profile');

    $response->assertOk();

    $data = $response->json('data');

    expect($data)->not->toHaveKey('password')
        ->not->toHaveKey('remember_token')
        ->not->toHaveKey('stripe_customer_id')
        ->not->toHaveKey('stripeCustomerId');
});

test('get profile returns null phone and dateOfBirth when not set', function () {
    $user = User::factory()->create([
        'phone' => null,
        'date_of_birth' => null,
    ]);

    $response = actingAs($user)->getJson('/api/account/profile');

    $response->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveKey('phone')
        ->toHaveKey('dateOfBirth')
        ->and($data['phone'])->toBeNull()
        ->and($data['dateOfBirth'])->toBeNull();
});

test('get profile returns 401 without authentication', function () {
    getJson('/api/account/profile')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| PATCH /api/account/profile — Name
|--------------------------------------------------------------------------
*/

test('update profile updates name only (partial update)', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'jane@finalcut.test',
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'name' => 'New Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'jane@finalcut.test');

    $user->refresh();
    expect($user->name)->toBe('New Name');
});

/*
|--------------------------------------------------------------------------
| PATCH /api/account/profile — Email
|--------------------------------------------------------------------------
*/

test('update profile updates email only', function () {
    $user = User::factory()->create([
        'email' => 'old@finalcut.test',
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'email' => 'new@finalcut.test',
        'current_password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'new@finalcut.test');

    $user->refresh();
    expect($user->email)->toBe('new@finalcut.test');
});

test('update profile allows keeping own email unchanged', function () {
    $user = User::factory()->create([
        'email' => 'jane@finalcut.test',
    ]);

    // Same email → not "changing" → no current_password required.
    $response = actingAs($user)->patchJson('/api/account/profile', [
        'email' => 'jane@finalcut.test',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'jane@finalcut.test');
});

test('update profile requires current_password to change email (account-takeover hardening)', function () {
    $user = User::factory()->create(['email' => 'jane@finalcut.test']);

    actingAs($user)->patchJson('/api/account/profile', [
        'email' => 'changed@finalcut.test',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    expect($user->fresh()->email)->toBe('jane@finalcut.test');
});

test('update profile changes email with the correct current_password', function () {
    $user = User::factory()->create(['email' => 'jane@finalcut.test']); // factory password = 'password'

    actingAs($user)->patchJson('/api/account/profile', [
        'email' => 'changed@finalcut.test',
        'current_password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.email', 'changed@finalcut.test');

    expect($user->fresh()->email)->toBe('changed@finalcut.test');
});

test('update profile rejects email already taken by another user', function () {
    User::factory()->create(['email' => 'taken@finalcut.test']);
    $user = User::factory()->create(['email' => 'mine@finalcut.test']);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'email' => 'taken@finalcut.test',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('update profile email uniqueness is case-insensitive', function () {
    User::factory()->create(['email' => 'user@test.com']);
    $user = User::factory()->create(['email' => 'mine@finalcut.test']);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'email' => 'User@Test.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('update profile stores email as lowercase', function () {
    $user = User::factory()->create(['email' => 'old@finalcut.test']);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'email' => 'User@FinalCut.Test',
        'current_password' => 'password',
    ]);

    $response->assertOk();

    $user->refresh();
    expect($user->email)->toBe('user@finalcut.test');
});

/*
|--------------------------------------------------------------------------
| PATCH /api/account/profile — Phone & Date of Birth
|--------------------------------------------------------------------------
*/

test('update profile updates phone and date_of_birth', function () {
    $user = User::factory()->create([
        'phone' => null,
        'date_of_birth' => null,
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'phone' => '+15559876543',
        'date_of_birth' => '1985-03-20',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.phone', '+15559876543');

    $data = $response->json('data');
    expect($data['dateOfBirth'])->toContain('1985-03-20');

    $user->refresh();
    expect($user->phone)->toBe('+15559876543')
        ->and($user->date_of_birth->format('Y-m-d'))->toBe('1985-03-20');
});

test('update profile rejects invalid date format for date_of_birth', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'date_of_birth' => 'not-a-date',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('date_of_birth');
});

test('update profile rejects future date_of_birth', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'date_of_birth' => '2099-01-01',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('date_of_birth');
});

/*
|--------------------------------------------------------------------------
| PATCH /api/account/profile — Password
|--------------------------------------------------------------------------
*/

test('update profile updates password when current_password is correct', function () {
    $user = User::factory()->create([
        'password' => 'old-password-123',
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'current_password' => 'old-password-123',
        'password' => 'N3w-Pr0file-Pass!',
        'password_confirmation' => 'N3w-Pr0file-Pass!',
    ]);

    $response->assertOk();

    $user->refresh();
    expect(Hash::check('N3w-Pr0file-Pass!', $user->password))->toBeTrue();
});

test('update profile rejects password change without current_password', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'password' => 'N3w-Pr0file-Pass!',
        'password_confirmation' => 'N3w-Pr0file-Pass!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('current_password');
});

test('update profile rejects a long but non-complex new password', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    actingAs($user)->patchJson('/api/account/profile', [
        'current_password' => 'correct-password',
        'password' => 'alllowercaseee',
        'password_confirmation' => 'alllowercaseee',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('update profile rejects password change with wrong current_password', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'current_password' => 'wrong-password',
        'password' => 'N3w-Pr0file-Pass!',
        'password_confirmation' => 'N3w-Pr0file-Pass!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('current_password');
});

test('update profile rejects password shorter than 8 chars', function () {
    $user = User::factory()->create([
        'password' => 'old-password-123',
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'current_password' => 'old-password-123',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('update profile requires password_confirmation when password provided', function () {
    $user = User::factory()->create([
        'password' => 'old-password-123',
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'current_password' => 'old-password-123',
        'password' => 'N3w-Pr0file-Pass!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('update profile returns updated profile in response body', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'phone' => null,
    ]);

    $response = actingAs($user)->patchJson('/api/account/profile', [
        'name' => 'Updated Name',
        'phone' => '+15551112222',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'dateOfBirth',
                'avatarUrl',
                'loyaltyPoints',
                'loyaltyTier',
                'premierExpiry',
                'createdAt',
            ],
        ])
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.phone', '+15551112222');
});

test('update profile returns 401 without authentication', function () {
    patchJson('/api/account/profile', ['name' => 'Test'])->assertUnauthorized();
});
