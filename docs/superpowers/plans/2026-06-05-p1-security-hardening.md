# P1 Security Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remediate the confirmed P1 (medium) findings from the 2026-06 Final Cut ultrareview — auth/session hardening, admin privilege-escalation gaps, payment message hygiene, API/contract correctness, data-model FK/convention fixes, missing security tests, and infra/CI hardening — as one reviewable PR stacked on the merged P0 work.

**Architecture:** Each finding was re-grounded against the current post-P0 code (branch `feat/p1-security-hardening`, which already carries the P0 Held→Confirmed booking rewrite). Fixes are ordered low-risk → high-risk in seven batches (A–G); a batch is a commit boundary. Two P1 items are intentionally **out of scope** (see "Deferred"): the booking_seats partial-unique-index (its own stacked PR) and `per_user_limit` *enforcement* (we hide the field instead).

**Tech Stack:** Laravel 13 / PHP 8.4 (Pest), Filament 5, Nuxt 4 / Vue 3 (Vitest), PostgreSQL 18, nginx/Docker Compose, Stripe PHP SDK.

---

## Conventions for this plan

- **TDD, Pest-first** (per CLAUDE.md). Write the failing test, watch it fail, implement minimally, watch it pass, commit.
- **Migrations edited in place** (pre-launch is still active — P0 edited migrations in place; the booking_seats partial-index is the *one* documented additive exception, deferred out of this plan). Editing a migration in place requires a **`make fresh`** before re-running affected suites.
- **Money stays integer cents.** **`rem` not `px`** on any frontend CSS. **No `#FFFFFF`**; focus rings use the gold double-ring (`box-shadow: 0 0 0 0.125rem var(--surface), 0 0 0 0.25rem var(--secondary)`).
- **Pint gotcha (repeatedly hit in P0):** Pint strips a freshly-added `use ...;` import if the symbol isn't referenced yet. **Always add the import and its first usage in the same edit.**
- **Stale route cache gotcha:** run `php artisan optimize:clear` **before** any backend test run, or you get spurious 404s.

### Run-command cheat sheet (dev stack up via `make up`)

```bash
# Backend, full suites (from project root):
make test-backend            # whole backend suite
make test-backend-feature    # Feature only
make test-backend-unit       # Unit only
make admin-test              # Filament/admin-namespaced tests

# Backend, single filtered run (clears stale route cache first):
docker compose exec -u 1000 backend php artisan optimize:clear
docker compose exec -u 1000 backend php artisan test --filter=SomeTest

# After editing a migration in place:
make fresh                   # migrate:fresh + seed

# Frontend:
make test-frontend           # Vitest
docker compose exec -u 1000 frontend npx vitest run path/to/test   # single

# Style / static:
docker compose exec -u 1000 backend ./vendor/bin/pint
docker compose exec -u 1000 backend php -d memory_limit=512M vendor/bin/phpstan analyse
```

---

# Batch A — Security quick wins (backend + nginx; no wire-contract change, minimal test churn)

Commit at the end of the batch (or per task). These are additive guards; existing green tests stay green.

---

## Task A1: Gate Filament loyalty actions with `->authorize()` (privilege escalation) — **REFUTED on execution**

> **OUTCOME (2026-06-05):** Refuted against Filament 5.6.0. `Action::isHidden()` folds the `->visible()` predicate into the same gate as authorization, and a hidden/unauthorized mounted action is **not invocable** — proven empirically with a crafted-payload attack (authorized actor runs it; unauthorized does not; flipping `->visible(true)` makes the unauthorized call run). The current `->visible(can(perm))` is a genuine server-side boundary. **No code change**; kept two crafted-payload regression guards instead. See `docs/progress/security-hardening-p1.md` § A1.

`->visible()` only hides a Filament 5 header action; a direct Livewire `mountAction` call still runs it. An `ops` admin lacking `loyalty.adjust_*` can escalate a customer's points/tier despite the hidden button.

**Files:**
- Modify: `backend/app/Filament/Resources/UserResource.php` (adjustPointsAction ~266-309, upgradePremierAction ~311-344, revokePremierAction ~346-374)
- Test: `backend/tests/Feature/Admin/LoyaltyActionsTest.php`

- [ ] **Step 1 — Write the failing test.** Add to `LoyaltyActionsTest.php`, "Permission split" section:

```php
test('a users.view-only admin cannot invoke adjust_points by mounting it directly', function () {
    $actor = User::factory()->admin()->create();
    $actor->syncPermissions([\Spatie\Permission\Models\Permission::findByName('users.view', 'admin')]);
    $this->actingAs($actor, 'admin');

    $member = User::factory()->create(['loyalty_points' => 100]);

    $service = \Mockery::mock(\App\Services\LoyaltyService::class);
    $service->shouldNotReceive('adjustPoints');
    $this->app->instance(\App\Services\LoyaltyService::class, $service);

    \Livewire\Livewire::test(\App\Filament\Resources\UserResource\Pages\ViewUser::class, ['record' => $member->id])
        ->assertActionHidden('adjust_points')
        ->callAction('adjust_points', ['points_delta' => 500, 'reason' => 'unauthorized attempt'])
        ->assertForbidden();

    expect($member->fresh()->loyalty_points)->toBe(100);
});

test('a points-only admin cannot invoke upgrade_premier directly', function () {
    $actor = User::factory()->admin()->create();
    $actor->syncPermissions([
        \Spatie\Permission\Models\Permission::findByName('users.view', 'admin'),
        \Spatie\Permission\Models\Permission::findByName('loyalty.adjust_points', 'admin'),
    ]);
    $this->actingAs($actor, 'admin');

    $member = User::factory()->create(['loyalty_tier' => \App\Enums\LoyaltyTier::Member]);

    $service = \Mockery::mock(\App\Services\LoyaltyService::class);
    $service->shouldNotReceive('grantPremier');
    $this->app->instance(\App\Services\LoyaltyService::class, $service);

    \Livewire\Livewire::test(\App\Filament\Resources\UserResource\Pages\ViewUser::class, ['record' => $member->id])
        ->callAction('upgrade_premier')
        ->assertForbidden();

    expect($member->fresh()->loyalty_tier)->toBe(\App\Enums\LoyaltyTier::Member);
});
```

- [ ] **Step 2 — Run red.** `docker compose exec -u 1000 backend php artisan test --filter=LoyaltyActionsTest` → the new cases FAIL (action runs / no forbidden) before the fix. (Run `optimize:clear` first.)

- [ ] **Step 3 — Implement.** On each of the three actions add `->authorize(...)` mirroring the existing `->visible()` permission predicate, reusing the static helpers (`actorCanAdjustPoints()` / `actorCanAdjustTier()` at ~204-212). Keep the `->visible()` calls (they also encode record-state: Member→upgrade, Premier→revoke). Do **not** move the record-state predicate into `authorize()`.

```php
// adjustPointsAction():
    ->authorize(fn (): bool => self::actorCanAdjustPoints())
// upgradePremierAction() and revokePremierAction():
    ->authorize(fn (): bool => self::actorCanAdjustTier())
```

- [ ] **Step 4 — Run green.** Re-run the filter → new cases PASS; existing visibility/permission-split tests stay green (admin role holds both perms, so `authorize()` returns true). Assert on the *side effect* (service not called / value unchanged), not a specific HTTP code.

- [ ] **Step 5 — Commit** (batch commit at end of A, or `git commit -m "fix(admin): gate loyalty header actions with ->authorize()"`).

---

## Task A2: Constrain admin FileUploads to raster types + size cap (SVG-XSS / DoS)

Both `->image()` uploads accept `image/svg+xml` (XML → stored XSS off the public disk) and have no size cap.

**Files:**
- Modify: `backend/app/Filament/Resources/CalendarEventResource.php` (image_path FileUpload ~118-123)
- Modify: `backend/app/Filament/Resources/MenuItemResource.php` (image_url FileUpload ~68-72)
- Test: `backend/tests/Feature/Admin/Resources/FileUploadValidationTest.php` (new)

- [ ] **Step 1 — Write the failing test** (Livewire upload assertion is the robust path):

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => actingAsAdmin());

test('CalendarEvent image rejects SVG and accepts webp', function () {
    Storage::fake('public');
    \Livewire\Livewire::test(\App\Filament\Resources\CalendarEventResource\Pages\CreateCalendarEvent::class)
        ->set('data.image_path', UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'))
        ->assertHasFormErrors(['image_path']);

    \Livewire\Livewire::test(\App\Filament\Resources\CalendarEventResource\Pages\CreateCalendarEvent::class)
        ->set('data.image_path', UploadedFile::fake()->image('ok.webp'))
        ->assertHasNoFormErrors(['image_path']);
});

test('MenuItem image rejects SVG', function () {
    Storage::fake('public');
    \Livewire\Livewire::test(\App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem::class)
        ->set('data.image_url', UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'))
        ->assertHasFormErrors(['image_url']);
});
```

(Confirm the exact Create page class names by globbing `backend/app/Filament/Resources/CalendarEventResource/Pages/` and `.../MenuItemResource/Pages/`. Add `use` for `UploadedFile`/`Storage` alongside their first use — Pint.)

- [ ] **Step 2 — Run red.** `--filter=FileUploadValidation` → SVG case FAILS (currently accepted).

- [ ] **Step 3 — Implement.** On both FileUpload fields:

```php
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->maxSize(5120) // 5 MB, in KB
```

For `MenuItemResource` also add `->visibility('public')` to make the public-serving intent explicit (matches CalendarEventResource).

- [ ] **Step 4 — Run green.** Filter passes. No existing happy-path resource test sets a real upload, so none break.

- [ ] **Step 5 — Commit.**

---

## Task A3: Scope `AdminUserProvider::retrieveById/retrieveByToken` to active admins (defense in depth)

Only `retrieveByCredentials` is overridden, so a session/remember-token for a now-disabled admin still rehydrates at the provider layer (the panel-level `canAccessPanel()` compensating control still blocks access — this is depth).

**Files:**
- Modify: `backend/app/Auth/AdminUserProvider.php`
- Test: `backend/tests/Feature/Admin/Auth/LoginTest.php`

- [ ] **Step 1 — Write the failing test:**

```php
test('retrieveById returns null for a user without an active AdminProfile', function () {
    $provider = \Illuminate\Support\Facades\Auth::guard('admin')->getProvider();

    $customer = User::factory()->create();
    expect($provider->retrieveById($customer->id))->toBeNull();

    $admin = User::factory()->admin()->create();
    expect($provider->retrieveById($admin->id))->not->toBeNull();

    $disabled = User::factory()->admin()->disabled()->create();
    expect($provider->retrieveById($disabled->id))->toBeNull();
});

test('retrieveByToken returns null for a non-admin', function () {
    $provider = \Illuminate\Support\Facades\Auth::guard('admin')->getProvider();
    $token = \Illuminate\Support\Str::random(60);

    $customer = User::factory()->create();
    $customer->forceFill(['remember_token' => $token])->save();
    expect($provider->retrieveByToken($customer->id, $token))->toBeNull();

    $admin = User::factory()->admin()->create();
    $admin->forceFill(['remember_token' => $token])->save();
    expect($provider->retrieveByToken($admin->id, $token))->not->toBeNull();
});
```

- [ ] **Step 2 — Run red.** `--filter=LoginTest` → new cases FAIL (disabled/customer still hydrate).

- [ ] **Step 3 — Implement** in `AdminUserProvider`:

```php
public function retrieveById($identifier)
{
    return $this->scopeToActiveAdmin(parent::retrieveById($identifier));
}

public function retrieveByToken($identifier, $token)
{
    return $this->scopeToActiveAdmin(parent::retrieveByToken($identifier, $token));
}
```

- [ ] **Step 4 — Run green.** New cases pass; existing credential/panel tests unaffected (they exercise `retrieveByCredentials` via `Auth::attempt`).

- [ ] **Step 5 — Commit.**

---

## Task A4: `Permissions-Policy` — allow geolocation on the customer vhost only

`geolocation=()` disables a feature `useGeolocation` actively uses; the location re-ordering is silently dead in prod.

**Files:**
- Modify: `nginx/templates/conf.d/default.conf.template:64`
- (Do **not** touch `admin.conf.template` — admin has no geolocation use.)

- [ ] **Step 1 — Edit.** Change line 64:

```
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(self)" always;
```

- [ ] **Step 2 — Verify config renders + header present.**

```bash
make down && make up   # re-render templates
docker compose exec nginx nginx -t
curl -skI https://finalcut.test/ | grep -i permissions-policy   # shows geolocation=(self)
curl -skI https://admin.finalcut.test/ | grep -i permissions-policy   # still geolocation=()
```

- [ ] **Step 3 — CI guard (optional but recommended).** Add a grep assertion to the nginx-template CI step: `grep -q 'geolocation=(self)' nginx/templates/conf.d/default.conf.template && grep -q 'geolocation=()' nginx/templates/conf.d/admin.conf.template`.

- [ ] **Step 4 — Commit.**

---

## Task A5: Fix malformed error envelope on Movie/Showtime 404s

Four call sites pass a flat dict → `{"errors":{"message":...}}` instead of the contract's `{"errors":[{"message":...}]}`; the frontend's `Array.isArray(data.errors)` check then drops the real message.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/MovieController.php:52` and `:63`
- Modify: `backend/app/Http/Controllers/Api/MovieShowtimesController.php:36`
- Modify: `backend/app/Http/Controllers/Api/ShowtimeController.php:25`
- Test: `MovieControllerTest.php`, `MovieShowtimesApiTest.php`, `ShowtimeControllerTest.php`
- Do **NOT** change the base `errorResponse` helper (≈40 callers already pass the correct shape).

- [ ] **Step 1 — Tighten existing 404 tests** (they only assert status today):

```php
// MovieControllerTest — unknown slug:
$this->getJson('/api/movies/nonexistent-movie')
    ->assertNotFound()
    ->assertJsonPath('errors.0.message', 'Movie not found')
    ->assertJsonStructure(['errors' => [['message']]]);
// MovieShowtimesApiTest — unknown movie slug → 'Movie not found'
// ShowtimeControllerTest — non-existent showtime → 'Showtime not found'
```

- [ ] **Step 2 — Run red.** Pre-fix `errors.0.message` resolves to null (object, not array) → FAIL.

- [ ] **Step 3 — Implement** — wrap each payload in an outer array:

```php
return $this->errorResponse([['message' => 'Movie not found']], 404);     // MovieController:52,63 & MovieShowtimesController:36
return $this->errorResponse([['message' => 'Showtime not found']], 404);  // ShowtimeController:25
```

- [ ] **Step 4 — Run green.** All three suites pass.

- [ ] **Step 5 — Commit.**

---

## Task A6: Sanitize raw Stripe `InvalidRequestException` messages (info leak)

`InvalidRequestException::getMessage()` is returned verbatim to clients and not `report()`ed — the inverse of correct. Keep `CardException` user-facing (decline reasons are meant to be shown) but document the deliberate split.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/BookingController.php` (store catch ~288-291, confirm catch ~518-519)
- Modify: `backend/app/Http/Controllers/Api/GiftCardController.php` (purchase ~121-132, confirm ~188-195)
- Modify: `backend/app/Http/Controllers/Api/PaymentMethodController.php` (~33-34, 52-53, 84-85)
- Test: `BookingControllerTest.php`, `GiftCardControllerTest.php`, `PaymentMethodControllerTest.php`
- Maybe modify: `backend/tests/Helpers/FakeStripeService.php` (add an `InvalidRequestException` thrower if absent)

- [ ] **Step 1 — Write failing tests.** For each controller, drive `createPaymentIntent` (or the relevant call) to throw `Stripe\Exception\InvalidRequestException` carrying a secret-ish string, assert 400 + generic copy + `Exceptions::assertReported(InvalidRequestException::class)` + the raw string is absent. Example (booking store):

```php
use Illuminate\Support\Facades\Exceptions;
use Stripe\Exception\InvalidRequestException;

test('store maps a Stripe InvalidRequestException to a generic 400 without leaking the raw message', function () {
    Exceptions::fake();
    $fixture = $this->createShowtimeWithSeats();
    $this->fakeStripe()->shouldThrowInvalidRequest('pi_secret_internal_xyz');

    $resp = $this->postJson("/api/locations/{$fixture['location']->slug}/bookings", $this->bookingPayload($fixture));

    $resp->assertStatus(400)
        ->assertJsonPath('errors.0.message', 'We could not process your payment. Please try again or contact support.');
    expect($resp->json('errors.0.message'))->not->toContain('pi_secret_internal_xyz');
    expect(\App\Models\Booking::count())->toBe(0); // Held discarded
    Exceptions::assertReported(InvalidRequestException::class);
});
```

If `FakeStripeService` lacks an InvalidRequest path, add: `public function shouldThrowInvalidRequest(string $msg): static { $this->invalidRequestMessage = $msg; return $this; }` and throw `InvalidRequestException::factory(message: $this->invalidRequestMessage)` from `createPaymentIntent` (check the SDK constructor — use the static `factory()`).

- [ ] **Step 2 — Run red.** New cases FAIL (raw message returned, not reported).

- [ ] **Step 3 — Implement.** In every `InvalidRequestException` catch, add `report($e);`, keep the existing compensating action (`discardHeldBooking`, `cacheHardFailure`, etc.), and return the generic message. For `GiftCardController::purchase`, pass the **generic** string into `cacheHardFailure` (so replays are sanitized too). Field stays `'payment'` (booking/gift-card) or `'stripe'` (payment-method). Add a one-line comment at each `CardException` catch: `// Stripe decline messages are intentionally surfaced to the cardholder.`

- [ ] **Step 4 — Run green.** Grep first for any test asserting a *specific* InvalidRequest message string and update it to the generic copy.

- [ ] **Step 5 — Commit batch A** (or this task).

---

# Batch B — Auth / session hardening (expect test-fixture churn)

---

## Task B1: Strong password policy via `Password::defaults()` + breach check

Three surfaces use bare `min:8`. Configure one shared default; gate `->uncompromised()` (live HIBP call) behind production so tests/CI never hit the network.

**Files:**
- Modify: `backend/app/Providers/AppServiceProvider.php` (`boot()`)
- Modify: `backend/app/Http/Requests/RegisterRequest.php:23`
- Modify: `backend/app/Http/Controllers/Api/AuthController.php:97` (note: `Password` short-name collides with the broker facade already imported on line 13 — use the FQN here)
- Modify: `backend/app/Http/Requests/UpdateProfileRequest.php:26`
- Test: `backend/tests/Feature/Api/AuthControllerTest.php`

- [ ] **Step 1 — Write failing tests:**

```php
test('register rejects a password without complexity', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Jane', 'email' => 'jane@finalcut.test',
        'password' => 'alllowercase12', 'password_confirmation' => 'alllowercase12',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});
// + analogous reset-password and update-profile complexity-rejection cases.
```

- [ ] **Step 2 — Run red.** New case FAILS (min:8 lowercase passes today).

- [ ] **Step 3 — Implement.** In `AppServiceProvider::boot()` (add `use Illuminate\Validation\Rules\Password;` with the call, same edit):

```php
Password::defaults(function () {
    $rule = Password::min(12)->mixedCase()->numbers()->symbols();
    return app()->isProduction() ? $rule->uncompromised() : $rule;
});
```

Then the three surfaces:
```php
// RegisterRequest:23
'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
// AuthController:97 (FQN to avoid the broker-facade name clash)
'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
// UpdateProfileRequest:26
'password' => ['sometimes', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
```

- [ ] **Step 4 — Update existing fixtures.** Replace every register/reset/update-profile **input** password fixture using `password123` / `new-password-123` with a compliant value, e.g. `Str0ng-Passw0rd!`. (Login/reset tests that *authenticate* with the factory literal `password` still work — validation doesn't run on login.) Run `make test-backend-feature` and fix every 422 the change surfaces.

- [ ] **Step 5 — HIBP test (production-gated, faked HTTP):**

```php
test('register rejects a breached password in production', function () {
    config(['app.env' => 'production']);
    \Illuminate\Support\Facades\Http::fake(['*pwnedpasswords*' => \Illuminate\Support\Facades\Http::response("<suffix-of-faked-hash>:42\r\n", 200)]);
    // post a password whose SHA1 suffix matches the faked line → assert 422
});
```

- [ ] **Step 6 — Run green** (`make test-backend`), then **commit.**

---

## Task B2: Prove password reset invalidates other devices

The reset callback already rotates `remember_token` and the password hash, so Sanctum's `AuthenticateSession` invalidates other sessions lazily on their next request. No instant Redis sweep is warranted (verifier: escalate only if product requires). Lock the invariant with a regression test.

**Files:**
- Test: `backend/tests/Feature/Api/AuthControllerTest.php`
- (No production code change unless the test reveals a gap; if so, ensure the reset callback rotates `remember_token` — it already does at ~102-107.)

- [ ] **Step 1 — Write the test** (model the second device by replaying the session cookie after `refreshApplication()`, the existing logout-test pattern):

```php
test('password reset invalidates other device sessions and rotates remember_token', function () {
    $user = User::factory()->create(['email' => 'r@finalcut.test', 'password' => bcrypt('password')]);
    $old = $user->fresh()->remember_token;
    // establish session A, capture cookies, assert /me 200
    // reset password with a valid token in a fresh app instance
    // replay session-A cookie → assert /me 401
    expect($user->fresh()->remember_token)->not->toBe($old);
});
```

- [ ] **Step 2 — Run.** If GREEN immediately, the invariant already holds — keep the regression test. If RED, add `'remember_token' => Str::random(60)` to the reset callback's `forceFill` and re-run.

- [ ] **Step 3 — Commit.**

---

## Task B3: Require `current_password` when the email is changed

Only password change requires re-auth today; a hijacked session can silently swap the account email (capturing future reset links). Require `current_password` only when the email actually **changes** value.

**Files:**
- Modify: `backend/app/Http/Requests/UpdateProfileRequest.php` (rules ~23-27; `Rule` already imported on line 7 — no new import)
- Test: `backend/tests/Feature/Api/AccountProfileTest.php`

- [ ] **Step 1 — Write failing tests:**

```php
test('email change without current_password is rejected', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patchJson('/api/account/profile', ['email' => 'new@finalcut.test'])
        ->assertStatus(422)->assertJsonValidationErrors('current_password');
});
test('email change with correct current_password succeeds', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $this->actingAs($user)->patchJson('/api/account/profile', ['email' => 'new@finalcut.test', 'current_password' => 'password'])
        ->assertOk();
    expect($user->fresh()->email)->toBe('new@finalcut.test');
});
test('resubmitting the same email needs no current_password', function () {
    $user = User::factory()->create(['email' => 'keep@finalcut.test']);
    $this->actingAs($user)->patchJson('/api/account/profile', ['email' => 'keep@finalcut.test'])->assertOk();
});
```

- [ ] **Step 2 — Run red.** First case FAILS (email change currently allowed).

- [ ] **Step 3 — Implement** in `UpdateProfileRequest`:

```php
protected function emailIsChanging(): bool
{
    return $this->filled('email')
        && strtolower((string) $this->input('email')) !== strtolower((string) $this->user()->email);
}
// rules():
'current_password' => [Rule::requiredIf(fn () => $this->filled('password') || $this->emailIsChanging()), 'current_password'],
```

(`prepareForValidation()` already lowercases `email`; compare lowercased current email to avoid case false-positives.)

- [ ] **Step 4 — Update existing tests** that change email with no current_password (e.g. "updates email only", "email uniqueness is case-insensitive", "stores email as lowercase") to add `'current_password' => 'password'`. Keep "keeping own email unchanged" with no current_password. `make test-backend-feature` → green.

- [ ] **Step 5 — Commit batch B.**

---

# Batch C — API / data correctness

---

## Task C1: Booking lookup also matches the account email

`lookup()` (now ~424-441 post-rewrite) filters `where('guest_email', $email)`, so a member's booking (`guest_email NULL`) is unfindable. Broaden to the user's email via the relation; email stays the shared secret.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/BookingController.php` (lookup ~424-441)
- Test: `backend/tests/Feature/Api/BookingControllerTest.php` (lookup block after ~877)

- [ ] **Step 1 — Failing test:**

```php
test('lookup finds a member booking via the account email', function () {
    $fixture = $this->createShowtimeWithSeats();
    $user = User::factory()->create(['email' => 'member@finalcut.test']);
    $booking = \App\Models\Booking::factory()->create([
        'user_id' => $user->id, 'guest_email' => null, 'showtime_id' => $fixture['showtime']->id,
    ]);
    $this->getJson('/api/bookings/lookup?'.http_build_query([
        'confirmation_code' => $booking->confirmation_code, 'email' => $user->email,
    ]))->assertOk()->assertJsonPath('data.id', $booking->id);
});
test('lookup with a wrong email returns 404', /* ... same code, different email → assertNotFound */);
```

- [ ] **Step 2 — Run red.** FAILS (guest_email NULL never matches).

- [ ] **Step 3 — Implement:**

```php
$email = $request->query('email');
$booking = Booking::with(self::BOOKING_RELATIONS)
    ->where('confirmation_code', $request->query('confirmation_code'))
    ->where(function ($q) use ($email) {
        $q->where('guest_email', $email)
          ->orWhereHas('user', fn ($u) => $u->where('email', $email));
    })
    ->first();
```

- [ ] **Step 4 — Run green.** Existing guest-path tests stay green. **Commit.**

---

## Task C2: 3DS confirm re-validates the promo amount (refund + 409 on drift)

In the 3DS path the PaymentIntent is sized in `store()`; `confirm()` Phase C writes the cached discount verbatim and only re-checks promo redeemability, not `amount`. An admin editing the promo mid-3DS yields a stale charge silently. Since the captured amount is already fixed, the correct fix is **detect drift and refund+409**, not re-price.

**Files:**
- Modify: `backend/app/Http/Controllers/Api/BookingController.php` (confirm Phase C ~550-569)
- Maybe modify: `backend/app/Exceptions/PromoCodeNotConsumableException.php` (add `REASON_AMOUNT_CHANGED`)
- Test: `backend/tests/Feature/Api/BookingControllerTest.php` (3DS section ~885)

- [ ] **Step 1 — Failing test:**

```php
test('3DS confirm refunds and 409s when the promo amount changed during the 3DS window', function () {
    $fixture = $this->createShowtimeWithSeats();
    $fakeStripe = $this->fakeStripe()->shouldRequire3ds();
    // POST booking with WELCOME5 → assert requiresAction true
    \App\Models\PromoCode::where('code', 'WELCOME5')->update(['amount' => 1000]); // was $5
    // drive confirm to succeed, POST /bookings/confirm
    // assert 409, errors.0.field === 'promoCode', no Confirmed booking, refund recorded for the PI
});
```

- [ ] **Step 2 — Run red.** FAILS (booking finalizes at stale discount).

- [ ] **Step 3 — Implement.** In Phase C, after rehydrating `$promo` under the locked transaction, recompute and compare before saving:

```php
$expectedDiscount = $promo
    ? $this->promoCodeService->calculateDiscount($promo, $pendingData['subtotal'])
    : 0;
$cachedPromoDiscount = $pendingData['discount'] - $giftCardAmount;
if ($cachedPromoDiscount !== $expectedDiscount) {
    throw new PromoCodeNotConsumableException(PromoCodeNotConsumableException::REASON_AMOUNT_CHANGED);
}
```

The existing catch (~609-616) already does `refundOrReport($paymentIntentId)`, forgets the cache, and returns 409 — so add the new reason constant + message and let it fire. Keep the comparison inside the Phase C transaction (shares the promo row lock).

- [ ] **Step 4 — Run green** + add a green-path test (unchanged promo still books at 201, discount 500). **Commit.**

---

## Task C3: Reject `price_multiplier <= 0` (service guard + DB CHECK)

`0.00` → free seats; negative → Stripe error. Filament `minValue(0)` still allows `0.00` and is bypassable via the service.

**Files:**
- Create: `backend/app/Exceptions/InvalidPriceMultiplierException.php`
- Modify: `backend/app/Services/AuditoriumService.php` (`updateSectionConfig` loop ~222-247)
- Modify: `backend/database/migrations/2026_04_23_100000_create_auditorium_sections_table.php` (in place — add CHECK; add `use Illuminate\Support\Facades\DB;`)
- Modify: `backend/app/Filament/Resources/AuditoriumResource.php:123` (`minValue(0)` → `minValue(0.01)`)
- Test: `backend/tests/Feature/Admin/Services/AuditoriumServiceIntegrationTest.php`

- [ ] **Step 1 — Failing tests:** reject `0`, reject `-1.50`, reject updating an existing section to `0` (assert rollback), and a raw `DB::table('auditorium_sections')->insert([... 'price_multiplier' => 0])` throws `QueryException` (proves the CHECK).

- [ ] **Step 2 — Run red.**

- [ ] **Step 3 — Implement.** New exception; guard at the top of the loop:

```php
$multiplier = $row['price_multiplier'] ?? 1.00;
if ((float) $multiplier <= 0) {
    throw new InvalidPriceMultiplierException($row['name'] ?? '', $multiplier);
}
```

Migration `up()` after the create: `DB::statement('ALTER TABLE auditorium_sections ADD CONSTRAINT auditorium_sections_price_multiplier_positive CHECK (price_multiplier > 0)');`; `down()`: `DB::statement('ALTER TABLE auditorium_sections DROP CONSTRAINT IF EXISTS auditorium_sections_price_multiplier_positive');`. Then `make fresh`.

- [ ] **Step 4 — Run green** (`--filter=AuditoriumServiceIntegration`; existing multipliers are all >0). **Commit.**

---

## Task C4: Hide the unenforced `per_user_limit` admin field

Decision: **hide** (no enforcement, no new schema). Leave the nullable column in place (pre-launch, harmless).

**Files:**
- Modify: `backend/app/Filament/Resources/PromoCodeResource.php` (remove the `TextInput::make('per_user_limit')` block ~102-106)
- Test: `backend/tests/Feature/Admin/Resources/PromoCodeResourceTest.php`

- [ ] **Step 1 — Failing test:**

```php
test('the promo form does not expose the unenforced per_user_limit field', function () {
    \Livewire\Livewire::test(\App\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode::class)
        ->assertFormFieldDoesNotExist('per_user_limit');
});
```

- [ ] **Step 2 — Run red.** FAILS (field present).

- [ ] **Step 3 — Implement.** Delete the `per_user_limit` `TextInput` block. Grep `PromoCodeResourceTest`/permission test for any `.set('data.per_user_limit', ...)` and remove those lines.

- [ ] **Step 4 — Run green** + **commit batch C.**

---

# Batch D — Frontend contract, a11y, and docs

---

## Task D1: FeaturedSlide contract — camelCase keys + safe CTA href (coordinated, atomic)

The resource emits snake_case (the lone outlier) **and** `cta_href` is rendered unvalidated (`javascript:` URL gap). Fix both in one atomic change so the wire contract turns over once.

**Files:**
- Modify: `backend/app/Http/Resources/FeaturedSlideResource.php` (~22-29)
- Modify: `backend/app/Filament/Resources/FeaturedSlideResource.php` (~90 — tighten the URL validator: replace `FILTER_VALIDATE_URL` with explicit `parse_url` scheme check allowing only http/https or leading-slash)
- Modify: `frontend/app/types/featured-slide.ts`
- Modify: `frontend/app/components/home/HomeFeaturedCarousel.vue` (BRAND_FALLBACK ~14-21, template bindings ~166-188 + a `safeCtaHref` guard)
- Test: `backend/tests/Feature/FeaturedSlideApiTest.php`, `frontend/tests/components/home/HomeFeaturedCarousel.test.ts`, `frontend/tests/composables/useFeaturedSlides.test.ts`

- [ ] **Step 1 — Backend: failing test.** In `FeaturedSlideApiTest`, rename key assertions to camelCase (`subHeadline`, `imageUrl`, `ctaLabel`, `ctaHref`) and add `->assertJsonMissing(['image_url'])` style negative. Add an admin-validation Pest case: a `javascript:` `cta_href` fails validation; `https://...` and `/path` pass.

- [ ] **Step 2 — Run red.**

- [ ] **Step 3 — Backend implement:**

```php
// Http/Resources/FeaturedSlideResource.php
return [
    'id' => $this->id,
    'headline' => $this->headline,
    'subHeadline' => $this->sub_headline,
    'imageUrl' => AssetUrl::resolve($this->image_url),
    'ctaLabel' => $this->cta_label,
    'ctaHref' => $this->cta_href,
];
```
Tighten the Filament validator closure: `$scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME)); return str_starts_with($value, '/') || in_array($scheme, ['http', 'https'], true);`

- [ ] **Step 4 — Frontend: failing tests.** In `HomeFeaturedCarousel.test.ts` add: `cta_href: 'javascript:alert(1)'` → no `.carousel__cta` rendered; `https://example.com` → renders; `/movies` → renders. Update fixtures to camelCase keys.

- [ ] **Step 5 — Frontend implement.** Update the type + BRAND_FALLBACK + bindings to camelCase, and add the guard:

```ts
function safeCtaHref(href: string | null): string | null {
  if (!href) return null
  const trimmed = href.trim()
  if (trimmed.startsWith('/')) return trimmed
  try { const u = new URL(trimmed); return (u.protocol === 'http:' || u.protocol === 'https:') ? trimmed : null }
  catch { return null }
}
```
Bind `:to="safeCtaHref(slide.ctaHref)"` inside `v-if="slide.ctaLabel && safeCtaHref(slide.ctaHref)"`.

- [ ] **Step 6 — Run green** both suites (`make test-backend-feature` + `make test-frontend`). This is a breaking contract change — backend + 3 frontend files land together. **Commit.**

---

## Task D2: A11y — PaymentBay button-group + gift-card focus-visible rings

PaymentBay is a broken tab pattern (3/4 disabled, no `aria-controls`/tabpanel); two gift-card inputs strip `outline` with no `:focus-visible` replacement (WCAG 2.4.7).

**Files:**
- Modify: `frontend/app/components/booking/CheckoutPaymentBay.vue` (~182-292 — demote `role="tablist"`→`role="group"`, drop `role="tab"`/`aria-selected`; keep `.method`/`.method--disabled` classes)
- Modify: `frontend/app/components/content/GiftCardComposer.vue` (add `.composer__custom-input:focus-visible` ring ~526-537)
- Modify: `frontend/app/components/content/GiftCardBalanceStrip.vue` (add `:focus-visible` to `.gift-card-balance-strip__input` ~124 and `__btn` ~137-151)
- Test: `frontend/tests/components/booking/CheckoutPaymentBay.test.ts` + a style-presence assertion test for the focus rings

- [ ] **Step 1 — Failing test.** PaymentBay: assert `[role="group"][aria-label="Payment method"]` exists and no `[role="tab"]` remains (existing `.method` count tests stay green). Focus rings: a DOM-string/style test asserting each component's `<style>` contains a `:focus-visible` rule for the three selectors.

- [ ] **Step 2 — Run red.**

- [ ] **Step 3 — Implement.** Demote PaymentBay to a button group. Add the double-ring to each input/btn:

```css
.composer__custom-input:focus-visible,
.gift-card-balance-strip__input:focus-visible,
.gift-card-balance-strip__btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 0.125rem var(--surface), 0 0 0 0.25rem var(--secondary);
  border-radius: var(--radius-sm);
}
```

- [ ] **Step 4 — Run green** (existing PaymentBay/GiftCard behavior tests unaffected). **Commit.**

---

## Task D3: Reconcile `nuxt-auth-utils` docs vs reality (it's not installed)

Docs describe `nuxt-auth-utils` as the SSR auth-hydration layer; it isn't a dependency and nothing imports it. Real mechanism: `useState('auth:user')` + a `localStorage` marker (`fc:auth:session`) gating a `/api/auth/me` probe, with Sanctum cookies as the authority and protected routes `ssr: false`. Fix the **docs**, do not install the module.

**Files:**
- Modify: `docs/architecture/STATE_MANAGEMENT.md` (§ Auth)
- Modify: `docs/architecture/SITE_ARCHITECTURE.md` (Dependencies table — remove/annotate the `nuxt-auth-utils` row)
- Sweep: `docs/architecture/DATA_MODELS.md` + plan files for the same claim (annotate)
- Test: `frontend/tests/architecture/auth-mechanism.test.ts` (new guard)

- [ ] **Step 1 — Write the guard test:**

```ts
// reads frontend/package.json → assert 'nuxt-auth-utils' absent from dependencies
// reads nuxt.config.ts → assert modules has no auth module
```

- [ ] **Step 2 — Run green** (it already holds) — this pins the invariant against drift.

- [ ] **Step 3 — Rewrite the docs** to describe the implemented mechanism (state in `useState('auth:user')`; non-sensitive localStorage marker gates the `/me` probe; Sanctum cookie is authoritative; protected/account/purchase routes are `ssr: false` so there is no server-side auth hydration). Do **not** `deno/npm add nuxt-auth-utils`.

- [ ] **Step 4 — Commit batch D.**

---

# Batch E — Data-model migrations (edit in place → `make fresh` after each)

> Each task here edits an original migration. Run **`make fresh`** before the affected suite, and update the named factory in the **same** change or the suite red-walls.

---

## Task E1: Add the missing `booking_food_items.menu_item_id` FK (+ fix the factory)

No referential integrity today; `MenuItemResource` has a hard delete with no SoftDeletes, so line items can dangle. The snapshot row must survive menu deletion → `nullOnDelete` (column already nullable).

**Files:**
- Modify: `backend/database/migrations/2026_04_04_200011_create_booking_food_items_table.php:14`
- Modify: `backend/database/factories/BookingFoodItemFactory.php:22` (random `Str::uuid()` → `MenuItem::factory()`)
- Test: `backend/tests/Feature/Api/BookingFoodItemForeignKeyTest.php` (new)

- [ ] **Step 1 — Failing test:** create Booking + real MenuItem + BookingFoodItem; delete the MenuItem; `refresh()` → `menu_item_id` null while `name`/`unit_price`/`total_price` intact (nullOnDelete + snapshot survives). Second case: inserting with a non-existent `menu_item_id` throws `QueryException` (FK exists).

- [ ] **Step 2 — Fix the factory first** (else every BookingFoodItem insert violates the new FK): `'menu_item_id' => MenuItem::factory()` (add `use App\Models\MenuItem;` with the usage).

- [ ] **Step 3 — Implement migration:** `$table->foreignUuid('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();`

- [ ] **Step 4 — `make fresh`**, then `--filter=BookingFoodItem` + the existing `BookingFoodItemTest` / `AccountOrdersTest` → green.

- [ ] **Step 5 — Commit.**

---

## Task E2: Stop cascade-deleting audit/snapshot rows (booking_seats + loyalty_adjustments)

`booking_seats.seat_id` cascade nukes price/section snapshots on seat regeneration; `loyalty_adjustments.user_id` cascade destroys the loyalty audit trail on user delete. **Both columns are NOT nullable today** → must be made nullable before `nullOnDelete`.

**Files:**
- Modify: `backend/database/migrations/2026_04_04_200010_create_booking_seats_table.php:15`
- Modify: `backend/database/migrations/2026_04_23_000000_create_loyalty_adjustments_table.php:14`
- Modify: `backend/app/Services/AuditoriumService.php` (widen `getRegenerationBlockers` to count **all** booking_seats, not just occupying statuses — so terminal-booking snapshots aren't silently cascaded under non-force regen)
- Test: `backend/tests/Feature/Admin/SeatRegenerationPreservesSnapshotsTest.php`, `backend/tests/Feature/Admin/LoyaltyAuditSurvivesUserDeleteTest.php` (new)

- [ ] **Step 1 — Failing tests.** (a) auditorium with seats + a Cancelled booking holding booking_seats; `generateSeats` (non-force) → historical booking_seats rows still exist (seat_id null, price/section intact). (b) User + LoyaltyAdjustment; hard-delete the User → adjustment row survives with `user_id` null and `points_delta`/`reason`/`change_type` unchanged.

- [ ] **Step 2 — Implement migrations (in place):**
```php
// booking_seats
$table->foreignUuid('seat_id')->nullable()->constrained()->nullOnDelete();
// loyalty_adjustments
$table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
```
Widen the regen blocker so it counts every `booking_seats` row referencing the auditorium's seats (drop the `occupyingStatuses()` filter there) — confirm `SeatAvailabilityService::checkAvailability` still filters to occupying statuses (it must, so a nulled terminal row no longer joins).

- [ ] **Step 3 — `make fresh`**, run `--filter='SeatRegeneration|LoyaltyAuditSurvives'` + the Auditorium/Seat/Loyalty cascade suites → green. Audit the seeder's Cancelled booking (`BookingSeeder`) interplay.

- [ ] **Step 4 — Commit.**

---

## Task E3: `promo_codes.is_active` boolean → `deactivated_at` nullable timestamp

The deactivate flow is an event-style transition; the convention says use a nullable timestamp (free "when" metadata). Keep an `is_active` accessor so most readers don't change.

**Files:**
- Modify: `backend/database/migrations/2026_04_24_100001_create_promo_codes_table.php:25,28`
- Modify: `backend/app/Models/PromoCode.php` (fillable, casts, add `isActive` accessor)
- Modify: `backend/app/Services/PromoCodeService.php:80` (`deactivate` → `$promo->deactivated_at = now()`)
- Modify: `backend/app/Filament/Resources/PromoCodeResource.php` (form toggle ~108, table column ~135, action visibility ~144)
- Modify: `backend/database/factories/PromoCodeFactory.php:24,51`
- Test: the existing promo suite + a new "deactivate stamps deactivated_at" assertion
- **Two raw-update call sites must change:** `BookingControllerTest.php:1169` and `BookingHeldLifecycleTest.php:94` do `update(['is_active' => false])` → change to `['deactivated_at' => now()]`.

- [ ] **Step 1 — Failing test.** In `PromoCodeServiceTest`, after `deactivate()`, assert `$promo->fresh()->deactivated_at` is non-null and recent.

- [ ] **Step 2 — Implement migration:** `$table->timestamp('deactivated_at')->nullable();` + index `['deactivated_at', 'expires_at']`. Model: swap fillable/casts (`'deactivated_at' => 'datetime'`) and add (import `Illuminate\Database\Eloquent\Casts\Attribute` with the method):

```php
protected function isActive(): Attribute
{
    return Attribute::get(fn () => $this->deactivated_at === null);
}
```
Service `deactivate()`: `$promo->deactivated_at = now();`. Filament: drop the create-time toggle (new codes are active by default); the table `IconColumn::make('is_active')->boolean()` and action visibility `$record->is_active` keep working via the accessor. Factory: default `'deactivated_at' => null`, `inactive()` → `['deactivated_at' => now()]`.

- [ ] **Step 3 — Update the two raw `update(['is_active' => false])` call sites** to `['deactivated_at' => now()]`, and any `PromoCodeResourceTest` `.set('data.is_active', ...)` lines.

- [ ] **Step 4 — `make fresh`**, run `--filter='PromoCode|BookingHeld|BookingController'` → green. **Commit batch E.**

---

# Batch F — Missing payment-path security tests (no production code change)

---

## Task F1: Non-3DS compensating-refund coverage

`store()` Phase C refunds an already-captured charge on finalize failure — the non-3DS branch has zero coverage. Inject the failure (you can't make a real balance race on one connection).

**Files:**
- Test: `backend/tests/Feature/Api/BookingCompensatingRefundTest.php` (new)

- [ ] **Step 1 — Write the test.** Partial-gift-card booking so `cardAmount > 0` and a charge is captured; bind a stub `GiftCardService` whose `redeemAgainstBooking()` throws (`$this->app->instance(GiftCardService::class, $stub)`); `shouldSucceed()`. Assert: `$fakeStripe->refundedPaymentIntents` has 1 entry whose PI id === the created PI; `Booking::where('status', Confirmed)->count() === 0`; the Held booking discarded (`Booking::count() === 0`). Add a sibling case targeting the `GiftCardBalanceChangedException` 409 branch (assert 409 + field `giftCardCode` + refund count 1).

- [ ] **Step 2 — Run** → green (the refund path already exists; this locks it). **Commit.**

---

## Task F2: Gift-card double-spend (HTTP layer)

The lock+re-validate contract that prevents two bookings draining the same card is unproven end-to-end. Model the race as sequential requests sharing the card (single connection can't truly parallelize).

**Files:**
- Test: `backend/tests/Feature/Api/GiftCardDoubleSpendTest.php` (new)

- [ ] **Step 1 — Write tests.** (a) GiftCard balance exactly enough for one booking; booking #1 (gift-card only) → 201, card depleted (balance 0, status depleted); booking #2 for a **different** seat with the same code and no `paymentMethodId` → refused (400/422 'Invalid or depleted gift card'); assert exactly one redemption ledger row and balance never < 0. (b) Partial: balance 1500; #1 redeems 1200 leaving 300; #2 (code + `pm_test_visa`) → mixed, discount 300, Stripe amount 900, final balance 0, two ledger rows summing to -1500.

- [ ] **Step 2 — Run** → green. **Commit batch F.**

---

# Batch G — Infra / CI hardening (config; manual/CI verification)

> Mostly compose/CI edits with no Pest/Vitest fit — verify via `docker compose config`, `nginx -t`, and CI.

---

## Task G1: Make PHPStan actually gate (without losing the SARIF upload)

`... || true` (`backend-phpstan.yml:63`) discards PHPStan's exit code, so it never fails a PR. The 89-entry baseline means a gate is regression-only and will pass on the current tree.

**Files:**
- Modify: `.github/workflows/backend-phpstan.yml`

- [ ] **Step 1 — Confirm a clean baselined exit locally:** `docker compose exec -u 1000 backend php -d memory_limit=512M vendor/bin/phpstan analyse` → exit 0 (baseline neutralizes the 89). If non-zero, stop and reconcile before touching CI.
- [ ] **Step 2 — Edit the workflow.** Keep `|| true` on the **JSON/SARIF-feeding** invocation, then add a **separate gating step** after the SARIF upload (carrying the same `if: steps.changes.outputs.php_changed == 'true'` condition) running `php -d memory_limit=512M vendor/bin/phpstan analyse` (table format, real exit code).
- [ ] **Step 3 — Verify** by pushing a branch with a deliberate non-baselined type error → the new gating step fails; revert. **Commit.**

---

## Task G2: Require DB/REDIS passwords on the prod-facing compose (fail loud)

`docker-compose.yml` (used in prod) bakes `${DB_PASSWORD:-secret}` / `${REDIS_PASSWORD:-redissecret}` into all three backend services. A prod deploy that forgets the vars silently runs weak creds.

**Files:**
- Modify: `docker-compose.yml` (backend 166/173, backend-worker 208/214, backend-scheduler 245/251)
- Keep `docker-compose.stack.yml` (14/36) dev defaults as-is (excluded from `PROD_COMPOSE`).
- Modify: ensure root `.env` / `backend/.env.production.example` document the required vars.

- [ ] **Step 1 — Edit** the six client lines to `${DB_PASSWORD:?DB_PASSWORD must be set}` / `${REDIS_PASSWORD:?REDIS_PASSWORD must be set}`.
- [ ] **Step 2 — Audit every `make` target** that runs `PROD_COMPOSE` (`local-prod-up`, etc.) — make sure root `.env` supplies the vars so dev still boots; the dev server password (stack.yml) and the client password must resolve to the **same** value.
- [ ] **Step 3 — Verify:** `DB_PASSWORD= REDIS_PASSWORD= docker compose -f docker-compose.yml -f docker-compose.prod.yml config` errors with the `:?` message; with vars set it renders the injected values. `make up` still boots. **Commit.**

---

## Task G3: Pin the `:latest` images (fail2ban, certbot)

Non-reproducible / supply-chain. Every other image is pinned.

**Files:**
- Modify: `docker-compose.yml:99` (`crazymax/fail2ban:latest`)
- Modify: `docker-compose.prod.yml:79` (`certbot/certbot:latest`)

- [ ] **Step 1 — Pin** to a concrete tag verified against the current jail/filter/action config and ACME flags (e.g. `crazymax/fail2ban:1.1.0`, `certbot/certbot:v3.3.0`); optionally append `@sha256:<digest>` captured via `docker inspect --format '{{index .RepoDigests 0}}'` after pulling.
- [ ] **Step 2 — Verify** the fail2ban jail still matches (the CI sample-log regen step) and a certbot `--dry-run` still parses. **Commit.**

---

## Task G4: Recycle the queue worker (`--max-time` / `--max-jobs` / `--memory`)

The single worker runs indefinitely with no memory recycling.

**Files:**
- Modify: `docker-compose.yml:195`

- [ ] **Step 1 — Edit** the command to:
```yaml
command: ["php", "artisan", "queue:work", "--tries=3", "--timeout=60", "--max-time=3600", "--max-jobs=1000", "--memory=256", "--backoff=5"]
```
(Defined once in the base file → covers dev, prod, local-prod. The scheduler `schedule:work` stays as-is.)
- [ ] **Step 2 — Verify** the prod render (`docker compose -f docker-compose.yml -f docker-compose.prod.yml config | grep -A1 backend-worker`) and that the worker exits-and-restarts after `--max-time`. **Commit.**

---

## Task G5: Mark the committed e2e `APP_KEY` as an intentional test key

Low risk (scoped to `APP_ENV=testing`, throwaway DB). Make intent explicit so it isn't mistaken for a leaked secret.

**Files:**
- Modify: `docker-compose.e2e.yml:51,82`

- [ ] **Step 1 — Choose one:** (a) require it — `${APP_KEY:?APP_KEY required for e2e}` and wire `key:generate --show` into the `make e2e` target; or (b) keep the default with an inline `# fixed, intentionally-public test key — never used where APP_ENV=production` comment on **both** lines (they must match).
- [ ] **Step 2 — Verify** `make e2e` still boots + Playwright passes. Optional CI grep: assert the literal test key never appears in `docker-compose.yml`/`docker-compose.prod.yml`/`docker-compose.stack.yml`. **Commit batch G.**

---

# Final verification (whole PR)

- [ ] `make fresh` (migrations were edited in place in Batch E).
- [ ] `make test-backend` → all green (target ≥ 1020 + the new P1 cases).
- [ ] `make admin-test` → green.
- [ ] `make test-frontend` → green (885 + new carousel/a11y/auth-guard cases).
- [ ] `docker compose exec -u 1000 backend ./vendor/bin/pint` → clean.
- [ ] `docker compose exec -u 1000 backend php -d memory_limit=512M vendor/bin/phpstan analyse` → exit 0 (gate now real).
- [ ] `make e2e` (frontend-touching batches D) → green.
- [ ] `curl -skI` both vhosts: customer `geolocation=(self)`, admin `geolocation=()`.
- [ ] Update `docs/progress/security-hardening-p1.md` as each batch lands.

---

# Deferred (NOT in this PR — tracked follow-ups)

1. **booking_seats partial-unique-index + Postgres trigger** (audit "deferred from P0"). Its own stacked PR off `feat/p1-security-hardening`: denormalized `occupies_seat` boolean (additive migration, co-located with the showtime EXCLUDE constraint), two triggers (BEFORE INSERT/UPDATE OF booking_id on booking_seats; AFTER UPDATE OF status on bookings — because 3 status write paths bypass Eloquent events), partial `UNIQUE INDEX ... WHERE occupies_seat`, and `SeatAvailabilityService::reserveSeats` catching `UniqueConstraintViolationException` → `SeatConflictException`. Concurrency test modeled on `ShowtimeConflictConcurrencyTest`. L effort / med risk.
2. **`per_user_limit` enforcement** (v2). We hid the field in C4; enforcement needs a `promo_code_id` FK on bookings + per-user occupying-count under lock + guest caveat.
3. **P2 batch** (input-validation hardening, doc drift, dead code, remaining a11y) — separate low-priority pass per the audit.
