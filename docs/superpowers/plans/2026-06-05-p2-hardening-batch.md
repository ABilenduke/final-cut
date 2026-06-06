# P2 hardening batch (API enum validation, input DoS guards, frontend util escaping, booking PII regression pins, infra tidies) — Final Cut

> **For agentic workers:** verified TDD dossier produced by the `design-three-hardening-features` workflow (8 mappers → 3 designers → 6 adversarial critics → 3 finalizers), empirically validated against the live `final_cut_test` Postgres. Steps are TDD: failing test first, minimal change, exact verify command.

**Goal:** P2 hardening batch: API enum-validation (→422), input/DoS length guards, frontend util escaping, booking PII/cross-location regression pins, and infra tidies (mailpit loopback, worker healthchecks, vitest, HSTS preload). Six audit items verified net-negative and CUT (documented).

**Tech Stack:** Laravel 13 (PHP 8.4, Pest) · PostgreSQL 18 · Nuxt 4 (Vitest) · Docker Compose · Filament 5

> **Implementation deviations from this dossier (authoritative = the shipped code + `docs/progress/p2-hardening-batch.md`):**
> - **HSTS preload SHIPS** (the dossier marked "GROUP F1 — CUT"). The user explicitly chose the widest scope; the `preload` token is added to both vhosts, documented as inert until the manual hstspreload.org submission.
> - **Healthchecks use `pgrep -f '[q]ueue:work'` / `'[s]chedule:work'`**, NOT the dossier's `pidof`. `pgrep` is BusyBox-provided in the php-alpine base (no `procps`), and the `[q]`/`[s]` bracket trick avoids the healthcheck shell self-matching. The prod healthchecks are disabled in the e2e overlay so they don't gate `up --wait`.
> - **safeJsonLd test-matrix**: the shipped test asserts that `&` is emitted as its escaped JSON form (a backslash-u-0026 sequence), not the raw ampersand. The dossier's matrix/summary lines that show a literal `&` are the pre-escape input, not the asserted output.
> - **`?per_page` is CLAMPED, not validated** (the dossier's A1 added a `per_page max:100` 422). The sitemap source (`frontend/server/api/__sitemap__/urls.get.ts`) fetches `/api/movies?per_page=500`; a 422 dropped every movie URL from sitemap.xml. MovieController validates ONLY `?status`; `per_page` keeps its long-standing clamp.
>
> Several embedded lines below (the "What SHIPS"/"CUT" summary, test-matrix, group descriptions) reflect the ORIGINAL dossier design, not the shipped code — they are kept as the design-history record; this note + `docs/progress/p2-hardening-batch.md` are authoritative for what actually shipped.

---


## Summary & verification notes

A multi-group hardening batch. After verifying every contested claim against ground truth, SIX of the original design's items were proven wrong or net-negative and are CUT or downgraded, and three more were reframed:

CUT entirely (verified harmful/redundant):
- GROUP J ($fillable loyalty removal) — CUT. `Factory::makeInstance` wraps creation in `Model::unguarded()` (vendor Factory.php:399,521), so factories are unaffected — but `DatabaseSeeder.php:34-55` uses `User::firstOrCreate([...email],[...loyalty fields])` which RESPECTS `$fillable`. No strict mode is enabled. Removing the fields silently turns the seeded `test@finalcut.test` premier fixture into a 0-point Member; every proposed factory-based test stays GREEN while `make fresh`/e2e/manual break. The real mass-assignment risk is already mitigated (RegisterRequest validates only name/email/password; LoyaltyService uses `increment()`/direct attr assignment + `save()`, never `$fillable`). Net-negative. CUT.
- GROUP E2 (auth:sanctum on /bookings/{id}) — CUT. `BookingControllerTest.php:822-831` ("unauthenticated user gets 404") would flip to 401, breaking a named test AND degrading the documented uniform-404 enumeration-safety convention (controller already 404s guests at BookingController.php:422). Zero real gain. CUT.
- GROUP I1 (/whats-on routeRules isr:900) — CUT. `whats-on-date-hydration.test.ts:36` explicitly asserts `NUXT_CONFIG` does NOT match `/whats-on.*isr` — /whats-on is INTENTIONALLY SSR-fresh for timezone-correct "today". Adding the rule breaks the existing guard. The doc (ISR 900) is the stale artifact. CUT (optional doc fix only).
- GROUP G2 (drop redundant users.email plain index) — CUT. `BookingController.php:445` and `CreateAdminUser.php:54,127` do case-sensitive raw `where('email', $email)` that uses the plain `users_email_index`, not the `lower(email)` unique. The plain index is NOT dead weight. CUT.
- GROUP F1 (HSTS preload) — *the dossier recommended CUT* (heavyweight, hard-to-reverse apex-wide HTTPS commitment). **OVERRIDDEN to SHIP per the user's explicit "everything + infra" choice** — the `preload` token is added to both vhosts, documented as inert until the manual hstspreload.org submission (see the deviations note at the top).
- GROUP K (AdminIpAllowlist code change) — CUT to test-only. Already fail-closed (null-IP guard at :69-74 runs after the empty-allowlist abort but before the string-typed `ipInCidr`). Add a regression pin; no code change.

REFRAMED:
- GROUP C (gift-card 'entropy' note) — INVERTED. Codes are generated `strtoupper(Str::random(8))` (GiftCardController:367) and looked up case-SENSITIVELY with no normalization (GiftCardController:246, GiftCardService:128). The real latent bug is lowercase user input → spurious 404, NOT entropy loss. Replaced with a doc comment describing the actual case-sensitivity contract (no behavior change in this batch).
- GROUP D2 (formatCurrency negatives) — kept pass-through (discount line items want '-$5.00'); pinned with tests + doc note. formatCurrencyParts returns `{whole, dec}` only (no 'mark' key — design prose was wrong).
- GROUP A1 (per_page) — moved the validator AHEAD of `resolveOptionalLocationSlug` (MovieController:17 resolves location first and 422s on bad location, which would mask a bad-status 422). **SHIPPED: only `?status` is validated; `?per_page` is CLAMPED, not 422'd** — the contract-change risk the dossier flagged materialized (the sitemap source requests `per_page=500`), so per the dossier's own fallback only `status` is validated. See the deviations note at the top.

What SHIPS (sound, low-risk): A1-A3 (enum query validation → 422), B1-B4 (max-length/count DoS guards), C (doc), D1 (safeJsonLd > and & escaping, defense-in-depth), D2 (formatCurrency negative + formatCurrencyParts test coverage), E1/E3 (booking PII/cross-location REGRESSION TESTS only, no logic change), F2 (Mailpit loopback bind), F4 (worker/scheduler healthchecks — **SHIPPED with `pgrep -f '[q]ueue:work'`/`'[s]chedule:work'`, NOT `pidof`**; `pgrep` is BusyBox-provided in the php-alpine base, and the `[q]`/`[s]` bracket avoids the healthcheck shell self-matching — disabled in the e2e overlay via `replicas: 0`), F5 (drop vitest --passWithNoTests), H1 (delete dead getShowtimes + its 2 tests), I2 (frontend .env.example).

Every implementation item is TDD: failing test first, minimal change, exact verify command.

---

## Files

- **[modify]** `backend/app/Http/Controllers/Api/MovieController.php` — A1: add validator for ?status (Rule::enum(MovieStatus)) + ?per_page (integer|min:1|max:100), placed BEFORE resolveOptionalLocationSlug so a bad-status 422 isn't masked by a bad-location 422. Add `use App\Enums\MovieStatus;` + `use Illuminate\Validation\Rule;` in the SAME edit (Pint strips orphan imports).
- **[modify]** `backend/app/Http/Controllers/Api/CalendarEventController.php` — A2: tighten ?type to Rule::enum(CalendarEventType) and ?accessibility to a closure rule that FILTERS empty segments (array_filter(array_map(trim,...))) before checking the allowlist — preserving current trailing-comma tolerance. Add `use Illuminate\Validation\Rule;` (CalendarEventType already imported).
- **[modify]** `backend/app/Http/Controllers/Api/FoodMenuController.php` — A3: add validator for ?category (Rule::enum(MenuCategory)) at top of index(Location,Request) before the query builds. Add `use App\Enums\MenuCategory;` + `use Illuminate\Validation\Rule;` same edit. crossLocation() untouched.
- **[modify]** `backend/app/Http/Requests/RegisterRequest.php` — B1: add 'max:72' to the password rule array (bcrypt truncates past 72 bytes).
- **[modify]** `backend/app/Http/Controllers/Api/AuthController.php` — B1: add 'max:72' to the inline reset-password rule (line ~98).
- **[modify]** `backend/app/Http/Requests/CreateBookingRequest.php` — B2: add 'max:20' to foodItems array rule; add 'max:512' to paymentMethodId rule.
- **[modify]** `backend/app/Http/Controllers/Api/BookingController.php` — B2: add 'max:512' to the inline confirm() paymentIntentId rule (line ~459). NO other logic change — E1/E3 are test-only.
- **[modify]** `backend/app/Http/Requests/RentalInquiryRequest.php` — B3: add 'max:1000' to guestCount (adapted from the brief's 'booking guestCount' — guestCount is a RENTAL field, not a booking field); add 'max:255' to email.
- **[modify]** `backend/app/Http/Requests/ContactRequest.php` — B4: add 'max:255' to email (consistency with Register/UpdateProfile).
- **[modify]** `backend/app/Services/GiftCardService.php` — C: doc-only. Add a comment above findByCode() documenting that codes are case-SENSITIVE (generated strtoupper, looked up verbatim) — the real contract, not the design's inverted 'entropy' framing. No behavior change.
- **[modify]** `frontend/app/utils/safeJsonLd.ts` — D1: add .replace(/>/g,'\\u003e') and .replace(/&/g,'\\u0026') to the chain (defense-in-depth; the existing < escape already blocks </script> breakout). Update docblock to say > and & are defense-in-depth.
- **[modify]** `frontend/app/utils/formatCurrency.ts` — D2: NO function change. Add a docblock note above formatCurrency that negatives pass through as '-$5.00' (discount line items) while formatCurrencyParts clamps to 0. Also fix the stale 'small currency mark' comment on formatCurrencyParts (it returns only {whole, dec}).
- **[modify]** `frontend/app/composables/useShowtimes.ts` — H1: delete the @deprecated getShowtimes method and remove it from the return object → `return { fetchByMovie, getShowtime }`.
- **[modify]** `frontend/.env.example` — I2: append NUXT_PUBLIC_SITE_URL and NUXT_PUBLIC_APP_TIME_ZONE (both read in nuxt.config.ts:69-70, absent from example). DO NOT add NUXT_SESSION_PASSWORD/SESSION_ENCRYPT — pinned forbidden by auth-mechanism.test.ts.
- **[modify]** `docker-compose.override.yml` — F2: prefix mailpit ports with 127.0.0.1 → '127.0.0.1:8025:8025' and '127.0.0.1:1025:1025' (mirrors stack.yml loopback pattern). In-network mailpit:1025 unaffected.
- **[modify]** `docker-compose.yml` — F4: add a healthcheck to backend-worker (probe 'queue:work') and backend-scheduler (probe 'schedule:work'). **SHIPPED with `pgrep -f '[q]ueue:work'`/`'[s]chedule:work'`** — the dossier assumed `pgrep`/`procps` was absent, but `pgrep` is BusyBox-provided in this php-alpine base (verified in a `--target production` build); the `[q]`/`[s]` bracket trick avoids the healthcheck shell self-matching. Disabled in the e2e overlay (`replicas: 0`).
- **[modify]** `.github/workflows/frontend-unit.yml` — F5: change line 60 to `run: npx vitest run` (drop --passWithNoTests so accidental zero-discovery fails CI).
- **[modify]** `backend/tests/Feature/Api/MovieControllerTest.php` — A1 tests: invalid status → 422; out-of-range per_page → 422; bad status still 422 even when location is also invalid (validation-order pin); valid status still 200.
- **[modify]** `backend/tests/Feature/Api/CalendarEventControllerTest.php` — A2 tests: unknown type → 422; valid type → 200; unknown accessibility tag → 422; valid CSV tags → 200; trailing-comma 'sensory_friendly,' → 200 (tolerance pin); whitespace ' a , b ' → 200.
- **[modify]** `backend/tests/Feature/Api/FoodMenuControllerTest.php` — A3 tests: invalid category → 422; valid category → 200.
- **[modify]** `backend/tests/Feature/Api/AuthControllerTest.php` — B1 tests: register + reset-password reject an OTHERWISE-VALID >72-char password (mixedCase+numbers+symbols) so max:72 is the ONLY firing rule — not an all-'a' string that fails the complexity rules regardless.
- **[modify]** `backend/tests/Feature/Api/BookingControllerTest.php` — B2 + E1 + E3 tests: over-long paymentMethodId → 422; >20 food line items (built from REAL seeded menu_items so exists: passes and max:20 fires) → 422; lookup wrong email → 404 with no PII leak (E1 pin); cross-location confirm → 410 with Booking::count() unchanged + a positive-control same-location confirm (E3). over-long confirm paymentIntentId → 422.
- **[modify]** `backend/tests/Feature/Api/RentalControllerTest.php` — B3 tests: guestCount=100000 → 422; over-long email → 422.
- **[modify]** `backend/tests/Feature/Api/ContactControllerTest.php` — B4 test: over-long email → 422.
- **[modify]** `backend/tests/Feature/Admin/AdminIpAllowlistTest.php` — K test (pin only): with a NON-empty allowlist set, a request with no resolvable client IP fails closed (403) AND the line-72 'no resolvable client IP' warning fires (assert the specific log, not just the 403). Build a request and remove REMOTE_ADDR so ->ip() is null.
- **[create]** `frontend/tests/utils/safeJsonLd.test.ts` — D1 tests: escapes < (no </script>); escapes > and &; escapes U+2028/U+2029; undefined → 'null'; FULL exact-string assertion for a known input (not just .toContain); round-trips after un-escaping.
- **[modify]** `frontend/tests/utils/formatCurrency.test.ts` — D2 tests: formatCurrency(-500) → '-$5.00' (pass-through pin); formatCurrencyParts(123456) → {whole:'1,234',dec:'56'}; (5) → {whole:'0',dec:'05'}; (-500) → {whole:'0',dec:'00'} (clamp pin). Update import to add formatCurrencyParts.
- **[modify]** `frontend/tests/composables/useShowtimes.test.ts` — H1: DELETE the two getShowtimes tests at lines 64-77 (and the section comment) — they reference the removed method.

---

## Tasks

### A1: Validate ?status and ?per_page on GET /api/movies (→422)

**Test scenarios:**
- GET /api/movies?status=banana → 422 with status validation error
- GET /api/movies?per_page=999999 → 422 with per_page validation error
- GET /api/movies?status=coming_soon → 200 (valid still works)
- GET /api/movies?status=banana&location=nonexistent → 422 on status (validation runs before location resolution; not masked by a location 422)

**Implementation:**

In MovieController::index, BEFORE the `$locationSlug = $this->resolveOptionalLocationSlug($request);` call (currently line 17 — CRITICAL: must precede it so a bad status isn't masked by the location 422), add:
```php
$validated = validator($request->query(), [
    'status' => ['nullable', Rule::enum(MovieStatus::class)],
    'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
])->validate();
```
Keep `$status = $request->input('status', 'now_showing');` and the existing `$perPage = min(max(...,1),100)` clamp (now only normalizes already-valid input). Add `use App\Enums\MovieStatus;` and `use Illuminate\Validation\Rule;` to the use-block IN THE SAME EDIT (Pint strips orphan imports). PRE-FLIGHT: `grep -rn "per_page" frontend/app` — confirm no caller intentionally over-requests per_page expecting the clamp; the validator changes 999999 from a silent 100-item 200 to a 422. None found in repo today, but verify before flipping. Idiom mirror: validator($request->query(),[...])->validate() from CalendarEventController.php:17.

**Verify:** `docker compose exec -u 1000 backend php artisan optimize:clear && docker compose exec -u 1000 backend php artisan test --filter=MovieControllerTest — all green, including the new 4 cases`

### A2: Validate ?type and ?accessibility on GET /api/calendar/events (→422), preserving trailing-comma tolerance

**Test scenarios:**
- ?type=not_a_type → 422
- ?type=special_event → 200
- ?accessibility=sensory_friendly,teleportation → 422
- ?accessibility=sensory_friendly,open_caption → 200
- ?accessibility=sensory_friendly, (trailing comma) → 200 (empty segment filtered, not 422 — matches current behavior where whereJsonContains('') matches nothing)
- ?accessibility= sensory_friendly , open_caption  (whitespace) → 200

**Implementation:**

In CalendarEventController::index, replace the `'type' => ['nullable','string']` and `'accessibility' => ['nullable','string']` lines (currently 20-21) with:
```php
$allowedTags = ['sensory_friendly', 'open_caption', 'audio_described'];
// inside the validator() array:
'type' => ['nullable', Rule::enum(CalendarEventType::class)],
'accessibility' => ['nullable', 'string', function ($attribute, $value, $fail) use ($allowedTags) {
    foreach (array_filter(array_map('trim', explode(',', $value))) as $tag) {
        if (! in_array($tag, $allowedTags, true)) {
            $fail("The {$attribute} field contains an unsupported tag: {$tag}.");
        }
    }
}],
```
The array_filter(array_map('trim',...)) skips empty segments so a trailing/leading comma stays 200 — matching the current consume path at line 44-49 which trims each tag and whereJsonContains-matches nothing for ''. CalendarEventType is already imported (line 5); add ONLY `use Illuminate\Validation\Rule;`. The downstream `explode(',', $validated['accessibility'])` (line 44) and `$validated['type'] === CalendarEventType::Showtime->value` (line 66) are unchanged — Rule::enum validates without transforming the string. DECISION: inline allowlist (not a new AccessibilityTag enum) keeps the diff small for this batch; note the option in the PR.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=CalendarEventControllerTest — all 6 new cases green`

### A3: Validate ?category on GET /api/locations/{location}/food-menu (→422)

**Test scenarios:**
- ?category=caviar → 422
- ?category=popcorn → 200

**Implementation:**

In FoodMenuController::index(Location $location, Request $request), at the TOP of the method before `$query = $location->menuItems()...` (currently line 64), add:
```php
validator($request->query(), [
    'category' => ['nullable', Rule::enum(MenuCategory::class)],
])->validate();
```
The existing `if ($request->filled('category'))` block (line 70-72) is unchanged — it now only runs on validated input. Add `use App\Enums\MenuCategory;` + `use Illuminate\Validation\Rule;` SAME edit. File has declare(strict_types=1) — keep imports clean. crossLocation() takes no query params — leave it. NOTE: location is route-model-bound (404 on bad slug happens before the method body), so no validation-order masking concern here unlike A1.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=FoodMenuControllerTest`

### B1: Cap password length at 72 (register + reset-password)

**Test scenarios:**
- register with a 200-char OTHERWISE-VALID password (e.g. ('Aa1!'×25)='Aa1!Aa1!...' 100 chars, mixedCase+numbers+symbols satisfied) → 422 on password (max:72 is the ONLY firing rule)
- reset-password with the same long-but-valid password → 422 on password

**Implementation:**

RegisterRequest.php line 24: `'password' => ['required', 'confirmed', 'max:72', Password::defaults()]`. AuthController.php resetPassword inline rules (line ~98): `'password' => ['required', 'confirmed', 'max:72', PasswordRule::defaults()]`. CRITICAL TEST DESIGN: Password::defaults() = min(12)->mixedCase()->numbers()->symbols() (AppServiceProvider.php:48). A str_repeat('a',200) fails mixedCase/numbers/symbols REGARDLESS of max:72 — a false-positive test. Build the long password from a repeating valid unit so the complexity rules pass and max:72 is the sole failure, e.g. `str_repeat('Aa1!', 25)` (100 chars). bcrypt truncates past 72 bytes so max:72 is the principled cap.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=AuthControllerTest`

### B2: Cap booking foodItems count (20), paymentMethodId/paymentIntentId length (512)

**Test scenarios:**
- store with paymentMethodId of 600 chars → 422 on paymentMethodId
- store with 21 food line items (each referencing a REAL seeded menu_item id) → 422 on foodItems (the count cap fires, not foodItems.*.itemId exists:)
- confirm with paymentIntentId of 600 chars → 422

**Implementation:**

CreateBookingRequest.php: line 34 `'foodItems' => ['sometimes', 'array', 'max:20']`; line 37 `'paymentMethodId' => ['required_without:giftCardCode', 'nullable', 'string', 'max:512']`. BookingController.php confirm() inline (line ~459): `'paymentIntentId' => ['required', 'string', 'max:512']`. CRITICAL TEST DESIGN: the 21-item test MUST use real seeded menu_items (reuse the file's booking-payload helper at the top of BookingControllerTest.php to build a valid payload, then create 21 real MenuItem rows and reference their ids) — if the 21 items use random UUIDs, `exists:menu_items,id` on foodItems.*.itemId fails first and the assertion passes for the WRONG reason (masking whether max:20 works). Assert the error key is on `foodItems` not `foodItems.0.itemId`. Stripe pm_/pi_ IDs are ~30 chars; 512 is generous.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=BookingControllerTest`

### B3: Rental inquiry: guestCount upper bound (1000) + email max (255)

**Test scenarios:**
- guestCount=100000 → 422
- email of 260+ chars → 422
- (existing guestCount=0 → 422 at RentalControllerTest:100 still passes)

**Implementation:**

RentalInquiryRequest.php: line 21 `'guestCount' => ['required', 'integer', 'min:1', 'max:1000']`; line 23 `'email' => ['required', 'email', 'max:255']`. ADAPTATION NOTE: the brief said 'guestCount upper bound on bookings' — guestCount is NOT a booking field (CreateBookingRequest has none); it lives on RentalInquiryRequest. Apply here, NOT to CreateBookingRequest. 1000 is a sane DoS ceiling (largest auditorium is a few hundred seats). Reuse the file's validRentalPayload() helper.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=RentalControllerTest`

### B4: Contact: email max:255

**Test scenarios:**
- POST /api/contact with a 260+ char email → 422 on email

**Implementation:**

ContactRequest.php line 18: `'email' => ['required', 'email', 'max:255']` (mirrors RegisterRequest/UpdateProfileRequest). ContactControllerTest disables throttle in beforeEach (line 8) so no rate-limit interference. Reuse the file's validContactPayload() helper.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=ContactControllerTest`

### C: Gift-card code case-sensitivity DOC note (no behavior change)

**Test scenarios:**
- No test — doc-only per brief

**Implementation:**

GROUP C is INVERTED from the original design. Ground truth: codes are generated `'GC-'.strtoupper(Str::random(8))` (GiftCardController:367) and looked up case-SENSITIVELY with NO normalization (GiftCardController:246 `where('code', $request->query('code'))`; GiftCardService:128 `where('code', $code)`). There is ZERO case-collapse / entropy loss today. The latent issue is the OPPOSITE: a customer typing/pasting their code in lowercase gets a spurious 404. Add a comment above GiftCardService::findByCode():
```php
// CONTRACT: gift-card codes are case-SENSITIVE. They are generated uppercase
// (GC-XXXXXXXX via strtoupper) and matched verbatim here and in
// GiftCardController::balance. Do NOT add upper/lowercasing on EITHER side in
// isolation — generation and lookup must stay in the same case or codes stop
// resolving. If case-insensitive UX is desired later, normalize BOTH the
// generator and every lookup together, and add a lowercase-input test.
```
Do NOT ship the design's 'entropy shrinks' framing — it describes a risk that doesn't exist in this code. No behavior change in this batch.

**Verify:** `Visual review only (doc comment). No test.`

### D1: safeJsonLd: escape > and & (defense-in-depth) + first test file

**Test scenarios:**
- safeJsonLd({x:'</script>'}) does not contain '</script>' and contains '\\u003c'
- safeJsonLd({x:'a > b'}) contains '\\u003e' and not '> b'
- safeJsonLd({x:'a & b'}) contains '\\u0026' — FULL exact-string assert: safeJsonLd({x:'a & b'}) === '{"x":"a \\u0026 b"}'
- safeJsonLd({x:'  '}) === '{"x":"\\u2028\\u2029"}'
- safeJsonLd(undefined) === 'null'
- round-trip: un-escaping \\u003c/\\u003e/\\u0026 then JSON.parse equals the original value

**Implementation:**

frontend/app/utils/safeJsonLd.ts — extend the .replace chain (lines 13-16):
```ts
return json
  .replace(/</g, '\\u003c')
  .replace(/>/g, '\\u003e')
  .replace(/&/g, '\\u0026')
  .replace(/ /g, '\\u2028')
  .replace(/ /g, '\\u2029')
```
Update the docblock: the < escape already blocks </script> breakout; > and & are defense-in-depth (do NOT overstate as an XSS fix). Create frontend/tests/utils/safeJsonLd.test.ts (NO existing file — mirror the scaffold of frontend/tests/utils/slugify.test.ts: `import { describe, it, expect } from 'vitest'` + `import { safeJsonLd } from '~/utils/safeJsonLd'`). Include the FULL exact-string assert for 'a & b' (a .toContain('&') alone passes even if escaping is partially wrong elsewhere).

**Verify:** `docker compose exec -u 1000 frontend npx vitest run tests/utils/safeJsonLd.test.ts`

### D2: formatCurrency: pin negative pass-through + add formatCurrencyParts coverage (no code change)

**Test scenarios:**
- formatCurrency(-500) === '-$5.00' (deliberate pass-through for discount line items)
- formatCurrencyParts(123456) toEqual {whole:'1,234', dec:'56'}
- formatCurrencyParts(5) toEqual {whole:'0', dec:'05'}
- formatCurrencyParts(-500) toEqual {whole:'0', dec:'00'} (clamp)

**Implementation:**

NO function change. formatCurrency passes negatives through (Intl '-$5.00') — discount/refund line items want the leading minus; formatCurrencyParts clamps via Math.max(0,cents) because its consumers (GiftCardVisual.vue:18, GiftCardPreview.vue:37 — both verified to never display a negative) shouldn't show a minus. Add a docblock above formatCurrency documenting this deliberate divergence, and FIX the stale comment on formatCurrencyParts (line 15-19) that says 'a small currency mark' — the function returns ONLY {whole, dec}, no mark key (the '$' lives in the Vue templates). Extend frontend/tests/utils/formatCurrency.test.ts: update the import to `import { formatCurrency, formatCurrencyParts } from '~/utils/formatCurrency'` and add the 4 cases. The toEqual values are verified correct (Math.floor(123456/100).toLocaleString='1,234', 123456%100 padded='56').

**Verify:** `docker compose exec -u 1000 frontend npx vitest run tests/utils/formatCurrency.test.ts`

### E1: Booking lookup PII gating — REGRESSION TEST ONLY (no logic change)

**Test scenarios:**
- GET /api/bookings/lookup?confirmation_code=<valid>&email=wrong@example.com → 404 and response does NOT contain the real guest_email

**Implementation:**

NO code change. PRE-FLIGHT grep confirmed checkout.vue:119-122 reads `bookingData.guestEmail` to prefill the post-purchase email field, and the frontend Booking type (booking.ts:16) declares `guestEmail: string | null` — so making guestEmail conditional in BookingResource WOULD risk the guest confirmation flow. Therefore the field stays unconditionally present (gating is already correct: lookup is email+code-gated, show is owner-gated → uniform 404). Add a regression test pinning the gating: create a guest booking with guest_email='real@example.com' and a confirmation code, then lookup with the right code but wrong email → assertStatus(404)->assertJsonMissing(['guestEmail' => 'real@example.com']). This pins the no-enumeration / no-PII-leak property without touching logic.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=BookingControllerTest`

### E3: Cross-location confirm rejection — REGRESSION TEST ONLY (enforcement already exists)

**Test scenarios:**
- confirm against location B's URL for a paymentIntent whose pending showtime lives in location A → 410, AND Booking::count() unchanged
- positive control: confirm against the CORRECT location A succeeds end-to-end (proves the 410 is location-specific, not a generic pending-data/setup failure)

**Implementation:**

NO code change. Enforcement already exists: confirm() re-queries the showtime THROUGH $location (BookingController.php:477 whereHas auditorium location_id, returns 410 at :482 if not found) and again in Phase C (:548). The pending cache key is `pending_booking:{paymentIntentId}` with no location component, so the showtime-through-location re-query is what rejects mismatches. TEST SETUP (critical): seed the pending cache via the file's existing store-flow helper so the entry has the FULL shape confirm() dereferences (showtime_id, seat_ids, card_amount, total, gift_card_amount, gift_card_id) — do NOT hand-roll a partial Cache::put (an under-populated entry can error before reaching the location re-query, passing 410 for the wrong reason). Capture the real paymentIntentId, create location B, POST confirm against B → assertStatus(410) AND assert Booking::count() is unchanged. Pair with a positive-control confirm against location A (use mockStripeSuccess() from StripeHelper) asserting success. The 410 short-circuits before Stripe in Phase A but include the mock defensively for the positive control.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=BookingControllerTest`

### F2: Bind Mailpit ports to 127.0.0.1 (dev host-exposure fix)

**Test scenarios:**
- No automated test (compose-only). Manual: host can still reach http://localhost:8025; another LAN host cannot connect to :8025/:1025

**Implementation:**

docker-compose.override.yml lines 112-114: change
```yaml
    ports:
      - "127.0.0.1:8025:8025"
      - "127.0.0.1:1025:1025"
```
Mirrors the loopback pattern in docker-compose.stack.yml:7,30. In-network access (mailpit:1025 via service name) is unaffected by host port mapping — backend/.env.example:57-58 MAIL_HOST=mailpit/MAIL_PORT=1025 still works. Mailpit is dev-only (absent from docker-compose.prod.yml).

**Verify:** `make up && curl -sI http://localhost:8025 (still 200 from host). Confirm no prod regression: grep -L mailpit docker-compose.prod.yml (mailpit absent).`

### F4: Healthchecks for backend-worker and backend-scheduler (busybox-compatible)

**Test scenarios:**
- No automated test. Manual: docker inspect --format '{{.State.Health.Status}}' backend-worker → healthy after start_period

**Implementation:**

CRITICAL: the backend image is php:8.4.19-fpm-alpine3.23 (Dockerfile:11,43) — `procps`/`pgrep` are NOT installed; a `pgrep -f` healthcheck errors and the container is unhealthy forever. BusyBox provides `pidof` (works) but it matches a process NAME (php), not args, so it can't distinguish queue:work from schedule:work. Two safe options — pick per the team:
(a) Add `procps` to the runtime apk layer (Dockerfile:75 `apk add --no-cache ... procps`) then use `pgrep -f 'queue:work'` / `'schedule:work'`. Cleanest match but adds an apk dep across stages.
(b) Use BusyBox `ps -o args` (BusyBox ps ignores -ef but accepts -o): `["CMD-SHELL", "ps -o args 2>/dev/null | grep -q '[q]ueue:work'"]` for worker, `'[s]chedule:work'` for scheduler (the [q]/[s] bracket trick avoids matching grep itself).
RECOMMEND (b) — no image change. Add to docker-compose.yml backend-worker (block at line ~6) and backend-scheduler (~47):
```yaml
    healthcheck:
      test: ["CMD-SHELL", "ps -o args 2>/dev/null | grep -q '[q]ueue:work' || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 20s
```
VERIFY the probe in-container before committing: `docker compose exec backend sh -c "ps -o args | grep -q '[q]ueue:work'; echo $?"` → 0. If BusyBox ps doesn't accept -o args in this image, fall back to (a).

**Verify:** `make up && sleep 30 && docker inspect --format '{{.State.Health.Status}}' backend-worker backend-scheduler → both 'healthy'`

### F5: Drop --passWithNoTests from frontend CI

**Test scenarios:**
- frontend-unit workflow stays green on next push (20+ test files exist, so behavior unchanged today)

**Implementation:**

.github/workflows/frontend-unit.yml line 60: change `run: npx vitest run --passWithNoTests` → `run: npx vitest run`. With real tests present this changes nothing today; an accidental zero-discovery (bad glob/config) now fails CI loudly instead of going green.

**Verify:** `Push and confirm the frontend-unit job stays green; locally `docker compose exec -u 1000 frontend npx vitest run` exits 0 with tests discovered.`

### H1: Delete dead getShowtimes from useShowtimes + its two tests

**Test scenarios:**
- useShowtimes.test.ts green after deleting the two getShowtimes tests
- grep -rn getShowtimes frontend → no matches

**Implementation:**

PRE-FLIGHT confirmed: getShowtimes is @deprecated, exported (line 59), zero app callers, but TWO active tests exist (useShowtimes.test.ts:64-77 'builds the legacy location-scoped list URL' + 'passes date filter'). Removal REQUIRES deleting those two tests + the section comment — not just confirming none reference it. In frontend/app/composables/useShowtimes.ts: delete the @deprecated getShowtimes method (lines ~38-47) and remove it from the return → `return { fetchByMovie, getShowtime }` (line 59). In frontend/tests/composables/useShowtimes.test.ts: delete lines 64-77 (the section comment + both it() blocks).

**Verify:** `docker compose exec -u 1000 frontend npx vitest run tests/composables/useShowtimes.test.ts && grep -rn getShowtimes frontend (no output)`

### I2: Add NUXT_PUBLIC_SITE_URL + NUXT_PUBLIC_APP_TIME_ZONE to frontend/.env.example

**Test scenarios:**
- auth-mechanism.test.ts still passes (no session-encryption var added)

**Implementation:**

frontend/.env.example — append:
```
NUXT_PUBLIC_SITE_URL=https://finalcut.test
NUXT_PUBLIC_APP_TIME_ZONE=America/New_York
```
Both are read in nuxt.config.ts:69-70 (siteUrl default 'https://finalcut.test', appTimeZone 'America/New_York') and are currently absent from the 3-var example. HARD CONSTRAINT: do NOT add NUXT_SESSION_PASSWORD or SESSION_ENCRYPT — frontend/tests/architecture/auth-mechanism.test.ts pins that nuxt-auth-utils was never adopted; adding a session var breaks that guard and contradicts CLAUDE.md. The brief's 'SESSION_ENCRYPT=false dev example' is rejected as contradicted by ground truth.

**Verify:** `docker compose exec -u 1000 frontend npx vitest run tests/architecture/auth-mechanism.test.ts (still green)`

### K: AdminIpAllowlist null-IP fail-closed REGRESSION PIN (no code change)

**Test scenarios:**
- With a NON-empty allowlist set, a request with no resolvable client IP → 403 AND the 'no resolvable client IP' warning fires

**Implementation:**

NO code change — ground truth confirms the middleware is already fail-closed: the null-IP guard (AdminIpAllowlist.php:69-74) runs AFTER the empty-allowlist abort (:51-67) but BEFORE the string-typed ipInCidr loop (:76+), so null can never TypeError. Add a pin test in AdminIpAllowlistTest.php (so it inherits the file's production-env beforeEach at :22). The file's helper takes a string and sets REMOTE_ADDR — write a NEW inline test that builds the request WITHOUT REMOTE_ADDR:
```php
test('a request with no resolvable client IP fails closed with a non-empty allowlist', function (): void {
    Log::spy();
    config()->set('admin.ip_allowlist', '203.0.113.0/24'); // NON-empty → execution reaches the :69 null guard, not the :51 empty abort
    $middleware = new AdminIpAllowlist;
    $request = Request::create('/admin', 'GET');
    $request->server->remove('REMOTE_ADDR'); // ->ip() resolves to null
    expect(fn () => $middleware->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class, 'Access denied');
    Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'no resolvable client IP'));
});
```
CRITICAL: the non-empty allowlist is what routes execution to the :69 null guard rather than short-circuiting at the :51 empty abort — asserting the SPECIFIC :72 warning (not just a generic 403) proves the test exercised the right branch. VERIFY IN-CONTAINER that Request::create + server->remove('REMOTE_ADDR') yields ->ip()===null (Symfony defaults REMOTE_ADDR to 127.0.0.1; remove() should clear it). If ->ip() still returns 127.0.0.1 in this Laravel version, the branch is unreachable from a unit test → drop the test and document K as a verified no-op in the PR rather than shipping a test that passes via the wrong path.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=AdminIpAllowlist`

---

## Gotchas

- PINT IMPORT STRIPPING: every `use App\Enums\...;` and `use Illuminate\Validation\Rule;` for A1/A2/A3 MUST be added in the SAME Edit as its first usage, or Pint's --dirty run strips it as unused. After backend edits run `docker compose exec -u 1000 backend ./vendor/bin/pint --dirty`.
- ROUTE CACHE: run `docker compose exec -u 1000 backend php artisan optimize:clear` before any --filter run that touches controllers/routes, or stale route cache yields phantom 404s (per project memory).
- B1 FALSE-POSITIVE TRAP: Password::defaults() requires min(12)+mixedCase+numbers+symbols. An all-'a' long password fails those rules regardless of max:72, so the test would pass even if max:72 were omitted. Use str_repeat('Aa1!', 25) so complexity passes and max:72 is the sole failure.
- B2 EXISTS-MASKS-COUNT TRAP: the >20-food-items test must reference REAL seeded menu_item ids; random UUIDs fail `exists:menu_items,id` first, passing the assertion for the wrong reason and never exercising max:20. Assert the error is on `foodItems`, not `foodItems.0.itemId`.
- A1 VALIDATION ORDER: MovieController::index resolves location FIRST (line 17, returns 422 for a bad ?location). The new validator MUST be placed before that call or a bad-status 422 is masked by a bad-location 422. Add the explicit 'bad status + bad location → status 422' test to pin this.
- GROUP J IS CUT (do not implement): removing loyalty fields from User #[Fillable] silently corrupts DatabaseSeeder.php:34-55 firstOrCreate (which respects $fillable) — test@finalcut.test becomes Member/0pts. Factories use Model::unguarded so they'd stay green, hiding the break until make fresh/e2e. The real risk is already mitigated (RegisterRequest validates only name/email/password; LoyaltyService uses increment()/direct-attr+save()).
- GROUP E2 IS CUT (do not implement): adding auth:sanctum to /bookings/{id} flips the existing named test BookingControllerTest.php:822 ('unauthenticated user gets 404') to 401 and degrades the uniform-404 enumeration convention. The controller already 404s guests at :422.
- GROUP I1 IS CUT (do not implement): adding `'/whats-on': { isr: 900 }` to routeRules breaks whats-on-date-hydration.test.ts:36 which asserts NUXT_CONFIG does NOT match /whats-on.*isr — /whats-on is intentionally SSR-fresh for timezone-correct 'today'. The doc (ISR 900) is the stale artifact; optionally fix the doc, never the config.
- GROUP G2 IS CUT (do not implement): the plain users_email_index is NOT dead weight — BookingController.php:445 and CreateAdminUser.php:54,127 do case-sensitive raw where('email', $email) that uses it (not the lower(email) unique). Dropping it degrades those to seq scans.
- GROUP F1 IS CUT: HSTS `preload` is a hard-to-reverse apex-wide HTTPS commitment submitted to hstspreload.org — a go-live decision, not a tidy. Document the deliberate omission; HSTS already ships max-age=1y + includeSubDomains.
- GROUP C IS INVERTED from the brief: gift-card codes are case-SENSITIVE (generated strtoupper, looked up verbatim) — there is no entropy loss. The doc comment must describe the real case-sensitivity contract, not the brief's imaginary 'keyspace shrinks' risk.
- F4 PROBE: the Alpine backend image has no pgrep/procps. Use BusyBox-compatible `ps -o args | grep -q '[q]ueue:work'` (the [q] bracket avoids matching grep itself), or add procps to the Dockerfile. VERIFY the probe returns 0 in-container before committing.
- I2 FORBIDDEN VARS: do NOT add NUXT_SESSION_PASSWORD/SESSION_ENCRYPT to frontend/.env.example — pinned forbidden by auth-mechanism.test.ts. The brief's 'SESSION_ENCRYPT dev example' is rejected.
- E3 CACHE SHAPE: confirm() dereferences showtime_id, seat_ids, card_amount, total, gift_card_amount, gift_card_id from the pending cache. Seed via the store-flow helper, not a partial Cache::put, or the test 410s for the wrong reason.
- POSTGRES listen_addresses='*' MUST STAY (postgres/postgresql.conf:5) — it's the in-container interface for docker-network DB connections; narrowing it breaks every backend/worker/scheduler/e2e DB connection. Host exposure is already locked to 127.0.0.1:5432 in stack.yml. Not in scope.
- FRONTEND TEST ZOMBIES: if a vitest run hangs, restart the frontend container (docker compose restart frontend) before re-running (per project memory).

## Test matrix

- MovieControllerTest: ?status=banana → 422
- MovieControllerTest: ?per_page=999999 → 422
- MovieControllerTest: ?status=coming_soon → 200
- MovieControllerTest: ?status=banana&location=nonexistent → 422 on status (order pin)
- CalendarEventControllerTest: ?type=not_a_type → 422
- CalendarEventControllerTest: ?type=special_event → 200
- CalendarEventControllerTest: ?accessibility=sensory_friendly,teleportation → 422
- CalendarEventControllerTest: ?accessibility=sensory_friendly,open_caption → 200
- CalendarEventControllerTest: ?accessibility=sensory_friendly, (trailing comma) → 200
- CalendarEventControllerTest: ?accessibility= sensory_friendly , open_caption  (whitespace) → 200
- FoodMenuControllerTest: ?category=caviar → 422
- FoodMenuControllerTest: ?category=popcorn → 200
- AuthControllerTest: register with valid >72-char password → 422 on password (max:72 sole failure)
- AuthControllerTest: reset-password with valid >72-char password → 422 on password
- BookingControllerTest: store paymentMethodId 600 chars → 422
- BookingControllerTest: store 21 real-menu-item food lines → 422 on foodItems
- BookingControllerTest: confirm paymentIntentId 600 chars → 422
- BookingControllerTest: lookup wrong email → 404, no guest_email in body (E1 pin)
- BookingControllerTest: cross-location confirm → 410, Booking::count() unchanged (E3)
- BookingControllerTest: same-location confirm → success (E3 positive control)
- RentalControllerTest: guestCount=100000 → 422
- RentalControllerTest: email 260+ chars → 422
- ContactControllerTest: email 260+ chars → 422
- AdminIpAllowlistTest: null client IP + non-empty allowlist → 403 + 'no resolvable client IP' warning (K pin)
- safeJsonLd.test: </script> → no </script>, contains <
- safeJsonLd.test: 'a > b' → contains >, not '> b'
- safeJsonLd.test: 'a & b' → exact string {"x":"a & b"}
- safeJsonLd.test: U+2028/U+2029 → escaped exact string
- safeJsonLd.test: undefined → 'null'
- safeJsonLd.test: round-trip un-escape + JSON.parse equals original
- formatCurrency.test: formatCurrency(-500) → '-$5.00'
- formatCurrency.test: formatCurrencyParts(123456) → {whole:'1,234',dec:'56'}
- formatCurrency.test: formatCurrencyParts(5) → {whole:'0',dec:'05'}
- formatCurrency.test: formatCurrencyParts(-500) → {whole:'0',dec:'00'}
- useShowtimes.test: suite green after deleting the two getShowtimes tests
- auth-mechanism.test: still green after .env.example edit (no session var)

## Open risks

- A1 per_page contract change: validating (422) instead of clamping a large per_page is a public API behavior change. Pre-flight grep of frontend found no caller depending on the clamp, but a third-party/integration consumer could. If the team prefers, keep clamping per_page and validate ONLY status (drop the per_page test). Flag in PR.
- K test feasibility: depends on Symfony Request::create + server->remove('REMOTE_ADDR') yielding ->ip()===null in this Laravel version. If it still resolves 127.0.0.1, the null branch is unreachable from a unit test — drop the test and document K as a verified no-op rather than shipping a test that passes via the empty-allowlist path.
- F4 BusyBox ps -o args support: if this Alpine image's BusyBox ps rejects `-o args`, the recommended probe fails. Fallback is adding procps to the Dockerfile (option a). Must be verified in-container before commit; no automated coverage exists for healthchecks.
- GROUP C is the single doc-only item with no test — relies on reviewer reading the comment. The actual lowercase-input UX bug (customer pastes lowercase code → 404) is documented but NOT fixed in this batch; if the team wants the UX fix, it's a separate behavior change (normalize generation + every lookup together + a lowercase-input test).
- F2/F4/F5 (compose/CI/nginx) have no Pest/Vitest coverage — verified only via make up + curl + docker inspect. A green test suite does not prove these; manual verification is mandatory before declaring done.
- H2 (Movie*.vue fabricated press/trailer copy) deliberately EXCLUDED from this batch — it's design-adjacent (fabricated 'Sight & Sound'/'The Guardian' quotes that would mislead if shipped). Track as a separate finalcut-design ticket; do not bundle risky UI copy changes into a hardening batch.
- I3 (DATA_MODELS.md TS-snippet staleness: Seat.label, ShowtimeLocation address fields exist in code but not the doc) is an optional doc refresh, not blocking. Excluded from the executable batch.
- Full cross-stack gate `make test` must pass before merge (CLAUDE.md: zero failing tests). GROUP J's removal would have required a full-suite run anyway; since J is cut, the blast radius of this batch is contained to the touched controllers/requests/utils.
