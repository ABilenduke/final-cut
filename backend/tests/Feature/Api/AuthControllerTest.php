<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
*/

test('register creates user and returns user data with 201', function () {
    $response = postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'email', 'name', 'avatarUrl', 'loyaltyPoints', 'loyaltyTier', 'createdAt'],
        ])
        ->assertJsonPath('data.email', 'jane@finalcut.test')
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.loyaltyTier', 'member')
        ->assertJsonPath('data.loyaltyPoints', 0);

    $this->assertDatabaseHas('users', ['email' => 'jane@finalcut.test']);
});

test('register establishes authenticated session', function () {
    postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertStatus(201);

    getJson('/api/auth/me')->assertOk()
        ->assertJsonPath('data.email', 'jane@finalcut.test');
});

test('register rejects duplicate email with 422', function () {
    User::factory()->create(['email' => 'taken@finalcut.test']);

    postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'taken@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('register rejects weak password', function () {
    postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@finalcut.test',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('register rejects a long but non-complex password', function () {
    // 14 chars, all lowercase letters — passes min length but fails the
    // mixedCase / numbers / symbols policy.
    postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@finalcut.test',
        'password' => 'alllowercaseee',
        'password_confirmation' => 'alllowercaseee',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('register rejects a breached password in production (HIBP)', function () {
    // The ->uncompromised() breach check only runs in production. Force that
    // branch and fake the HIBP k-anonymity range response so no real network
    // call fires.
    app()->detectEnvironment(fn () => 'production');
    expect(app()->isProduction())->toBeTrue();

    $breached = 'Compr0mised-Pass!'; // meets the complexity policy
    $sha1 = strtoupper(sha1($breached));
    $suffix = substr($sha1, 5);

    Http::fake([
        'api.pwnedpasswords.com/*' => Http::response("{$suffix}:9000\r\nFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF:1", 200),
    ]);

    postJson('/api/auth/register', [
        'name' => 'Breach Test',
        'email' => 'breach@finalcut.test',
        'password' => $breached,
        'password_confirmation' => $breached,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('register rejects missing required fields', function () {
    postJson('/api/auth/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('register handles duplicate email race condition gracefully', function () {
    // Simulate a race: validation passes (email is unique at check time),
    // but another request inserts the same email before User::create() commits.
    // We use a model event to sneak in a duplicate row after validation but
    // before the INSERT, triggering a real UniqueConstraintViolationException.
    $email = 'race@finalcut.test';
    $inserted = false;

    User::creating(function (User $user) use ($email, &$inserted) {
        if (! $inserted && $user->email === $email) {
            $inserted = true;
            DB::table('users')->insert([
                'id' => Str::uuid()->toString(),
                'name' => 'Other Racer',
                'email' => $email,
                'password' => bcrypt('Str0ng-Passw0rd!'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $response = postJson('/api/auth/register', [
        'name' => 'Racer',
        'email' => $email,
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('register does not expose password in response', function () {
    $response = postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    $response->assertStatus(201);
    $data = $response->json('data');
    expect($data)->not->toHaveKey('password')
        ->not->toHaveKey('remember_token')
        ->not->toHaveKey('stripe_customer_id');
});

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

test('login returns user data and establishes session', function () {
    $user = User::factory()->create([
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ]);

    $response = postJson('/api/auth/login', [
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'john@finalcut.test')
        ->assertJsonPath('data.id', $user->id);

    getJson('/api/auth/me')->assertOk();
});

test('login rejects wrong password with 401', function () {
    User::factory()->create([
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ]);

    postJson('/api/auth/login', [
        'email' => 'john@finalcut.test',
        'password' => 'wrong-password',
    ])->assertStatus(401)
        ->assertJsonPath('errors.0.message', 'Invalid credentials');
});

test('login rejects nonexistent email with same 401 message', function () {
    postJson('/api/auth/login', [
        'email' => 'nobody@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ])->assertStatus(401)
        ->assertJsonPath('errors.0.message', 'Invalid credentials');
});

test('login regenerates session to prevent fixation', function () {
    User::factory()->create([
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ]);

    // Establish a real session by hitting the CSRF cookie endpoint
    $this->get('/api/sanctum/csrf-cookie')->assertNoContent();

    // Capture the pre-login session cookie value
    $preLoginSessionId = session()->getId();
    expect($preLoginSessionId)->not->toBeEmpty();

    $this->postJson('/api/auth/login', [
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ])->assertOk();

    expect(session()->getId())
        ->not->toBeEmpty()
        ->not->toBe($preLoginSessionId);
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

test('logout clears session and subsequent me returns 401', function () {
    User::factory()->create([
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ]);

    // Login via API
    $this->postJson('/api/auth/login', [
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ])->assertOk();

    $this->getJson('/api/auth/me')->assertOk();

    $this->postJson('/api/auth/logout')->assertOk()
        ->assertJsonPath('data.success', true);

    // After logout, auth should be cleared — use a fresh client to avoid stale state
    $this->refreshApplication();
    $this->getJson('/api/auth/me')->assertUnauthorized();
});

test('logout returns 401 without auth', function () {
    postJson('/api/auth/logout')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Me
|--------------------------------------------------------------------------
*/

test('me returns authenticated user data', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@finalcut.test',
    ]);

    actingAs($user)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.email', 'john@finalcut.test')
        ->assertJsonPath('data.id', $user->id);
});

test('me returns 401 without auth', function () {
    getJson('/api/auth/me')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Forgot Password
|--------------------------------------------------------------------------
*/

test('forgot password returns success and sends notification when email exists', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'john@finalcut.test']);

    postJson('/api/auth/forgot-password', ['email' => 'john@finalcut.test'])
        ->assertOk()
        ->assertJsonPath('data.success', true);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password notification contains frontend reset URL', function () {
    $user = User::factory()->create(['email' => 'john@finalcut.test']);

    $token = Password::createToken($user);
    $notification = new ResetPassword($token);

    $mail = $notification->toMail($user);

    $frontendUrl = config('app.frontend_url');
    $actionUrl = $mail->actionUrl;

    expect($actionUrl)->toStartWith("{$frontendUrl}/auth/reset-password")
        ->and($actionUrl)->toContain("token={$token}")
        ->and($actionUrl)->toContain('email=john%40finalcut.test');
});

test('forgot password returns success even when email does not exist', function () {
    Notification::fake();

    postJson('/api/auth/forgot-password', ['email' => 'nobody@finalcut.test'])
        ->assertOk()
        ->assertJsonPath('data.success', true);

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/

test('reset password updates password with valid token', function () {
    $user = User::factory()->create(['email' => 'john@finalcut.test']);

    $token = Password::createToken($user);

    postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'john@finalcut.test',
        'password' => 'N3w-Str0ng-Pass!',
        'password_confirmation' => 'N3w-Str0ng-Pass!',
    ])->assertOk()
        ->assertJsonPath('data.success', true);

    $user->refresh();
    expect(Hash::check('N3w-Str0ng-Pass!', $user->password))->toBeTrue();

    // Can login with new password
    postJson('/api/auth/login', [
        'email' => 'john@finalcut.test',
        'password' => 'N3w-Str0ng-Pass!',
    ])->assertOk();
});

test('reset password rejects invalid token', function () {
    User::factory()->create(['email' => 'john@finalcut.test']);

    postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => 'john@finalcut.test',
        'password' => 'N3w-Str0ng-Pass!',
        'password_confirmation' => 'N3w-Str0ng-Pass!',
    ])->assertStatus(422);
});

test('reset password rejects a long but non-complex password', function () {
    $user = User::factory()->create(['email' => 'john@finalcut.test']);
    $token = Password::createToken($user);

    postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'john@finalcut.test',
        'password' => 'alllowercaseee',
        'password_confirmation' => 'alllowercaseee',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('reset password rejects weak password', function () {
    $user = User::factory()->create(['email' => 'john@finalcut.test']);
    $token = Password::createToken($user);

    postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'john@finalcut.test',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('reset password rejects mismatched email', function () {
    $user = User::factory()->create(['email' => 'john@finalcut.test']);
    $token = Password::createToken($user);

    postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'wrong@finalcut.test',
        'password' => 'N3w-Str0ng-Pass!',
        'password_confirmation' => 'N3w-Str0ng-Pass!',
    ])->assertStatus(422);
});

// Security regression: a reset must invalidate other devices. There is no
// authenticated request at the guest reset endpoint, so Auth::logoutOtherDevices()
// is not usable; instead the reset rotates remember_token (kills "remember me"
// cookies) AND changes the password hash (Sanctum's AuthenticateSession evicts
// every other session on its next request via the hash mismatch). Lock both.
test('reset password rotates remember_token and changes the password hash', function () {
    $user = User::factory()->create(['email' => 'john@finalcut.test']);
    $user->forceFill(['remember_token' => 'old-remember-token-aaaaaaaaaaaaaaaaaaaa'])->save();
    $oldHash = $user->password;
    $token = Password::createToken($user);

    postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'john@finalcut.test',
        'password' => 'N3w-Str0ng-Pass!',
        'password_confirmation' => 'N3w-Str0ng-Pass!',
    ])->assertOk();

    $user->refresh();
    expect($user->remember_token)->not->toBe('old-remember-token-aaaaaaaaaaaaaaaaaaaa')
        ->and($user->password)->not->toBe($oldHash);
    expect(Hash::check('N3w-Str0ng-Pass!', $user->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Sanctum SPA Cookie Flow
|--------------------------------------------------------------------------
*/

test('full Sanctum SPA cookie flow works end-to-end', function () {
    $user = User::factory()->create([
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ]);

    // Step 1: Fetch CSRF cookie
    $csrfResponse = $this->get('/api/sanctum/csrf-cookie');
    $csrfResponse->assertNoContent();

    // Step 2: Login with CSRF token (Laravel test client carries cookies automatically)
    $loginResponse = $this->postJson('/api/auth/login', [
        'email' => 'john@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ]);
    $loginResponse->assertOk()
        ->assertJsonPath('data.email', 'john@finalcut.test');

    // Step 3: Access protected route with session (cookies persist within test)
    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'john@finalcut.test');
});

/*
|--------------------------------------------------------------------------
| Full Lifecycle
|--------------------------------------------------------------------------
*/

test('register → me → logout → login → me lifecycle', function () {
    // Register
    $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ])->assertStatus(201);

    // Me after register — session established
    $this->getJson('/api/auth/me')->assertOk()
        ->assertJsonPath('data.email', 'jane@finalcut.test');

    // Logout
    $this->postJson('/api/auth/logout')->assertOk();

    // Login again (same test client, stale auth cleared by guard re-evaluation)
    $this->postJson('/api/auth/login', [
        'email' => 'jane@finalcut.test',
        'password' => 'Str0ng-Passw0rd!',
    ])->assertOk();

    // Me after login
    $this->getJson('/api/auth/me')->assertOk()
        ->assertJsonPath('data.email', 'jane@finalcut.test');
});

test('register rejects an otherwise-valid password longer than 72 bytes', function () {
    // str_repeat('Aa1!', 25) = 100 chars and satisfies mixedCase+numbers+symbols,
    // so max:72 is the ONLY firing rule (not a complexity rule).
    $longValid = str_repeat('Aa1!', 25);

    postJson('/api/auth/register', [
        'name' => 'Too Long',
        'email' => 'toolong@example.com',
        'password' => $longValid,
        'password_confirmation' => $longValid,
    ])->assertStatus(422)->assertJsonValidationErrors(['password']);
});

test('reset-password rejects an otherwise-valid password longer than 72 bytes', function () {
    $longValid = str_repeat('Aa1!', 25);

    postJson('/api/auth/reset-password', [
        'token' => 'some-token',
        'email' => 'someone@example.com',
        'password' => $longValid,
        'password_confirmation' => $longValid,
    ])->assertStatus(422)->assertJsonValidationErrors(['password']);
});
