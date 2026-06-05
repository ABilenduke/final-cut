# Progress: P1 Security Hardening (Ultrareview Tier 2)

Source: ultrareview audit (`~/.claude/plans/i-want-you-re-compressed-axolotl.md`). Plan: `docs/superpowers/plans/2026-06-05-p1-security-hardening.md`. Branch: `feat/p1-security-hardening` off `feat/p0-security-hardening`. TDD, migrations edited in place (pre-launch). Baseline before P1: backend **1020** / frontend **885** green (per P0 journal).

Decisions locked at planning (2026-06-05): `per_user_limit` → **hide the field** (no enforcement); booking_seats partial-unique-index → **separate stacked PR** (not in this branch).

---

## Batch A — Security quick wins

### Task A1: Loyalty header actions `->authorize()` — **REFUTED** (no code change)
**Status:** ✅ Complete (as refutation + regression guards)
**Started:** 2026-06-05 **Completed:** 2026-06-05

#### Investigation
The audit claimed the three loyalty header actions (`adjust_points`, `upgrade_premier`, `revoke_premier` on `UserResource`/`ViewUser`) are gated only by `->visible()`, "which only hides; a direct Livewire call can still invoke" → privilege escalation for an `ops` admin lacking `loyalty.adjust_*`.

Grounded against **Filament 5.6.0** this is **refuted**:
- Read the Filament source: `Action::isVisible()` = `! isHidden()`; `isHidden()`/`isHiddenInGroup()` returns true when **either** the user's `->visible()` predicate is false **or** `isAuthorizedOrNotHiddenWhenUnauthorized()` is false. A hidden/unauthorized mounted action is not invocable.
- Empirically proved it with the **strongest** attack (a crafted Livewire payload that sets `mountedActions` directly and calls `callMountedAction`, bypassing the `mountAction` handshake):
  - Control: an **authorized** admin's crafted payload **runs** the action (points 100→600, 1 adjustment).
  - An **unauthorized** (`users.view`-only) admin's identical payload does **not** run (points stay 100, 0 adjustments, no exception).
  - Decisive isolation: temporarily flipping `adjustPointsAction` to `->visible(fn () => true)` (actor still unauthorized) made the crafted payload **run** → the `->visible()` permission predicate is precisely what blocks the call. Reverted.
- Conclusion: the current `->visible(fn () => can('loyalty.adjust_*'))` **is** a genuine server-side authorization boundary in v5.6, not mere hiding. Adding `->authorize()` would be redundant (same `isHidden` gate); adding it *with* `->authorizationNotification()` would make the action visible-but-disabled to unauthorized users, breaking the existing `assertActionHidden` tests for zero security gain.

#### Decision
- **No production change.** Did not add `->authorize()` (cargo-cult, redundant, risks UX regression).
- **Kept two regression guards** in `LoyaltyActionsTest.php` using the crafted-payload attack: an unauthorized `users.view`-only admin cannot run `adjust_points`, and a points-only admin cannot run `upgrade_premier`. They pass today and fail RED if anyone weakens the `->visible()` permission gate (e.g. to a record-state-only predicate), so the escalation gap can never silently open.

#### Verification
- `LoyaltyActionsTest`: **11 passed** (9 pre-existing + 2 new guards). Pint clean. `git diff` on `UserResource.php` empty (diagnostic reverted).

#### Files Changed
- `backend/tests/Feature/Admin/LoyaltyActionsTest.php` — 2 crafted-payload escalation regression guards + refutation comment.

### Task A2: FileUpload type/size constraints (SVG-XSS / DoS)
**Status:** ✅ Complete — 2026-06-05
- Both public-disk `FileUpload`s (`CalendarEventResource.image_path`, `MenuItemResource.image_url`) accepted the full `image/*` family (incl. `image/svg+xml`) and had no size cap. Added `->acceptedFileTypes(['image/jpeg','image/png','image/webp'])` + `->maxSize(5120)` (5 MB, raster-only — SVG excluded); added `->visibility('public')` to MenuItem to match CalendarEvent.
- New `tests/Feature/Admin/Resources/FileUploadValidationTest.php` (4): SVG rejected / raster accepted for both. (Container GD lacks webp generation → positive case uses jpeg.)
- Verification: 4 new pass; CalendarEvent+MenuItem resource suites 27 pass.

### Task A3: AdminUserProvider retrieveById/retrieveByToken (defense-in-depth)
**Status:** ✅ Complete — 2026-06-05
- Overrode `retrieveById()` + `retrieveByToken()` to apply the same `scopeToActiveAdmin()` filter as `retrieveByCredentials()`, so a session/remember-token for a disabled/never-admin user is rejected at the provider layer on every request (not just at login). `canAccessPanel()` remains the primary control.
- New tests in `LoginTest.php` (2). Verification: full LoginTest 13 pass.

### Task A4: Permissions-Policy geolocation=(self) (customer vhost only)
**Status:** ✅ Complete — 2026-06-05
- `nginx/templates/conf.d/default.conf.template:64` `geolocation=()` → `geolocation=(self)` (admin vhost left `geolocation=()`). Restored the silently-dead `useGeolocation` feature in prod.
- Verification: `nginx -t` ok; restarted nginx; `curl -skI` confirms customer `geolocation=(self)`, admin `geolocation=()`.

### Task A5: Malformed error envelope on Movie/Showtime 404s
**Status:** ✅ Complete — 2026-06-05
- `MovieController:52,63`, `MovieShowtimesController:36`, `ShowtimeController:25` passed a flat dict → `{"errors":{"message":…}}`; wrapped each in an outer array → canonical `{"errors":[{"message":…}]}`. (Did NOT touch the base `errorResponse` helper — ~40 callers already pass the correct shape.) The frontend's `Array.isArray(data.errors)` check now receives the real message instead of dropping it.
- Tightened 4 existing 404 tests with `assertJsonPath('errors.0.message', …)` + structure. Verification: 4 pass; Movie/Showtime suites 47 pass.

### Task A6: Sanitize raw Stripe InvalidRequestException messages
**Status:** ✅ Complete — 2026-06-05
- Every `InvalidRequestException` catch in `BookingController` (store+confirm), `GiftCardController` (purchase+confirm), `PaymentMethodController` (index/store/destroy) returned `$e->getMessage()` (integration-facing: param names/ids) and did NOT `report()` it — the inverse of correct. Now: `report($e)` + a generic client message. GiftCard `cacheHardFailure` stores the SANITISED message so idempotent replays don't leak either. `CardException` left user-facing by design (decline messages are meant for the cardholder) with an explanatory comment.
- Tests: flipped `BookingControllerTest` "invalid payment method" from asserting the leak to asserting generic + no-leak + `Exceptions::assertReported`; strengthened the GiftCard + PaymentMethod equivalents (GiftCard also asserts the idempotent replay stays sanitised). Verification: 3 new/updated pass; Booking+GiftCard+PaymentMethod suites 117 pass.

#### Batch A Files Changed
- `backend/app/Filament/Resources/CalendarEventResource.php`, `MenuItemResource.php` — FileUpload constraints.
- `backend/app/Auth/AdminUserProvider.php` — retrieveById/retrieveByToken overrides.
- `nginx/templates/conf.d/default.conf.template` — geolocation=(self).
- `backend/app/Http/Controllers/Api/{MovieController,MovieShowtimesController,ShowtimeController}.php` — error-envelope shape.
- `backend/app/Http/Controllers/Api/{BookingController,GiftCardController,PaymentMethodController}.php` — Stripe message sanitisation.
- Tests: `FileUploadValidationTest.php` (new), `LoginTest.php`, `MovieControllerTest.php`, `MovieShowtimesApiTest.php`, `ShowtimeControllerTest.php`, `BookingControllerTest.php`, `GiftCardControllerTest.php`, `PaymentMethodControllerTest.php`.

---

## Batch B — Auth / session hardening

### Task B1: Strong password policy via Password::defaults() + HIBP
**Status:** ✅ Complete — 2026-06-05
- Configured one shared policy in `AppServiceProvider::boot()`: `Password::min(12)->mixedCase()->numbers()->symbols()`, with `->uncompromised()` (live HIBP) gated behind `app()->isProduction()` so tests/CI never hit the network. The three surfaces (`RegisterRequest`, `AuthController::resetPassword`, `UpdateProfileRequest`) now use `Password::defaults()` instead of bare `min:8`.
- AuthController: aliased the rule import (`Password as PasswordRule`) to avoid the `Illuminate\Support\Facades\Password` broker collision; sequenced usage-before-import to dodge Pint's strip-on-unused.
- Fixture churn: upgraded weak INPUT passwords (`password123`→`Str0ng-Passw0rd!`, `new-password-123`→`N3w-Str0ng-Pass!`, `new-password-456`→`N3w-Pr0file-Pass!`) in AuthControllerTest / AccountProfileTest / PurchaseFlowIntegrationTest. Login-only fixtures untouched (validation doesn't run on login).
- Tests: 3 complexity-rejection cases (register/reset/profile) + 1 production HIBP case (forces `detectEnvironment('production')` + `Http::fake` on the pwnedpasswords range, computing the breached password's SHA1 suffix in-test).

### Task B2: Password reset invalidates other devices (regression guard)
**Status:** ✅ Complete — 2026-06-05 (test-only, no code change)
- Finding partly mooted by reality: the reset callback already rotates `remember_token` AND changes the password hash. `Auth::logoutOtherDevices()` is NOT usable from the guest reset endpoint (no authenticated request). Remember-me cookies die via the token rotation; active sessions die lazily via Sanctum's `AuthenticateSession` hash-mismatch on their next request. No instant Redis sweep (verifier: escalate only if product requires).
- Added a regression test asserting reset rotates `remember_token` and changes the password hash.

### Task B3: Require current_password to change email
**Status:** ✅ Complete — 2026-06-05
- `UpdateProfileRequest`: `current_password` now `Rule::requiredIf(password change OR email change)`. Added `emailIsChanging()` (compares lowercased input vs current email so a pure case-change of the same address is not treated as a change, keeping "keep own email unchanged" green).
- Tests: email change without current_password → 422; with correct current_password → OK; same-email submit needs none. Updated the two email-only happy-path tests to pass `current_password`.

#### Batch B Files Changed
- `backend/app/Providers/AppServiceProvider.php` — Password::defaults() policy.
- `backend/app/Http/Requests/RegisterRequest.php`, `UpdateProfileRequest.php` — Password::defaults() + email current_password rule.
- `backend/app/Http/Controllers/Api/AuthController.php` — reset uses Password::defaults() (aliased import).
- Tests: `AuthControllerTest.php`, `AccountProfileTest.php`, `PurchaseFlowIntegrationTest.php`.

---

## Batch C — API / data correctness

### Task C1: Booking lookup matches the account email
**Status:** ✅ Complete — 2026-06-05
- `BookingController::lookup()` matched `guest_email` only, so member bookings (user_id set, guest_email NULL) were unfindable. Broadened to `guest_email = email OR user.email = email` via `orWhereHas('user', …)`. Email stays the shared secret. Tests: member lookup via account email (RED→GREEN), wrong account email → 404.

### Task C2: 3DS confirm re-validates the promo discount
**Status:** ✅ Complete — 2026-06-05
- The PaymentIntent is sized in store(); confirm() Phase C wrote the cached discount verbatim and only re-checked promo redeemability, not amount → an admin editing a promo mid-3DS yielded a stale charge. Now Phase C re-derives `calculateDiscount(livePromo, subtotal)` under the lock and compares to the cached promo component (`discount - giftCardAmount`); on drift it throws new `PromoCodeNotConsumableException::REASON_AMOUNT_CHANGED`, which the existing catch refunds (compensating) + forgets the cache + 409 `promoCode`. Test asserts captured-then-refunded, no booking, promo not consumed, pending cleared.

### Task C3: price_multiplier > 0 (service guard + DB CHECK)
**Status:** ✅ Complete — 2026-06-05
- `AuditoriumService::updateSectionConfig` now rejects `price_multiplier <= 0` (covers create + update branches) via new `App\Exceptions\InvalidPriceMultiplierException`. Edited `create_auditorium_sections` migration **in place** to add a `CHECK (price_multiplier > 0)` constraint (defense in depth). Tightened Filament `minValue(0)` → `minValue(0.01)`. Tests: service rejects 0 / negative / update-to-0 (rollback proof) + DB CHECK rejects a raw insert (savepoint-wrapped). 20 AuditoriumService tests pass (the DB CHECK test confirms RefreshDatabase applied the in-place migration).

### Task C4: Hide the unenforced per_user_limit field
**Status:** ✅ Complete — 2026-06-05 (decision: hide, not enforce)
- Removed the `per_user_limit` `TextInput` from `PromoCodeResource` (no per-user redemption ledger exists in v1 → unenforceable; the nullable column stays, harmless). Test asserts the form field is absent.

#### Batch C Files Changed
- `backend/app/Http/Controllers/Api/BookingController.php` — lookup() user-email match + confirm() promo drift check.
- `backend/app/Exceptions/PromoCodeNotConsumableException.php` — REASON_AMOUNT_CHANGED.
- `backend/app/Exceptions/InvalidPriceMultiplierException.php` (new), `backend/app/Services/AuditoriumService.php` — multiplier guard.
- `backend/database/migrations/2026_04_23_100000_create_auditorium_sections_table.php` — CHECK constraint.
- `backend/app/Filament/Resources/AuditoriumResource.php` — minValue(0.01); `PromoCodeResource.php` — per_user_limit removed.
- Tests: `BookingControllerTest.php`, `AuditoriumServiceIntegrationTest.php`, `PromoCodeResourceTest.php`.

---

## Batch D — Frontend contract, a11y, docs

### Task D1: FeaturedSlide camelCase + safe cta_href (atomic)
**Status:** ✅ Complete — 2026-06-05
- Renamed the four FeaturedSlide API keys to camelCase (`subHeadline`/`imageUrl`/`ctaLabel`/`ctaHref`) in `FeaturedSlideResource` (the lone snake_case outlier), in lockstep with the frontend type, `HomeFeaturedCarousel.vue` (BRAND_FALLBACK + bindings), and both frontend test files. Backend asserts the legacy snake_case keys are gone.
- Hardened `cta_href` both layers: frontend `safeCtaHref()` allowlist (http(s)/relative only → a `javascript:` URL renders no link) and the Filament validator now parses the scheme (`parse_url`) instead of `FILTER_VALIDATE_URL` (which accepts `javascript:`). Tests: carousel g3 (javascript: → no link) + g4 (https renders); Filament rejects javascript:, accepts https/relative.

### Task D2: A11y — PaymentBay button-group + gift-card focus rings
**Status:** ✅ Complete — 2026-06-05
- `CheckoutPaymentBay`: demoted the invalid `role="tablist"` (1 enabled + 3 disabled, no tabpanel) to `role="group"`; dropped `role="tab"`/`aria-selected`; active method now via `aria-pressed`. `.method*` classes retained (existing tests green). New test asserts the button-group pattern + no `role="tab"`.
- `GiftCardComposer.__custom-input` and `GiftCardBalanceStrip.__input`/`__btn`: added the design-system gold double-ring `:focus-visible` (they stripped `outline` with no replacement → WCAG 2.4.7 fail). Source-level style-presence test guards them.

### Task D3: nuxt-auth-utils doc reconciliation
**Status:** ✅ Complete — 2026-06-05 (docs + guard test; module NOT installed)
- `nuxt-auth-utils` is documented as the SSR auth-hydration layer but was never adopted (absent from package.json, nothing imports it). The real mechanism: `useState('auth:user')` + `localStorage` marker `fc:auth:session` gating the `/api/auth/me` probe, Sanctum cookie authoritative, protected routes `ssr: false`. Corrected the authoritative docs (CLAUDE.md, STATE_MANAGEMENT.md, SITE_ARCHITECTURE.md incl. the NUXT_SESSION_PASSWORD env note, DATA_MODELS.md). Did NOT install the module. New `frontend/tests/architecture/auth-mechanism.test.ts` pins the invariant (no nuxt-auth-utils dep, no auth module).

#### Batch D Files Changed
- Backend: `app/Http/Resources/FeaturedSlideResource.php`, `app/Filament/Resources/FeaturedSlideResource.php`, tests `FeaturedSlideApiTest.php` + `Admin/Resources/FeaturedSlideResourceTest.php`.
- Frontend: `app/types/featured-slide.ts`, `app/components/home/HomeFeaturedCarousel.vue`, `app/components/booking/CheckoutPaymentBay.vue`, `app/components/content/GiftCardComposer.vue`, `app/components/content/GiftCardBalanceStrip.vue`; tests `HomeFeaturedCarousel.test.ts`, `CheckoutPaymentBay.test.ts`, `design-system/gift-card-focus-rings.test.ts` (new), `architecture/auth-mechanism.test.ts` (new).
- Docs: `CLAUDE.md`, `docs/architecture/{STATE_MANAGEMENT,SITE_ARCHITECTURE,DATA_MODELS}.md`.

---

## Batch E — Data-model migrations (edited in place → `make fresh`)

### Task E1: booking_food_items.menu_item_id FK
**Status:** ✅ Complete — 2026-06-05
- Added `foreignUuid('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete()` (snapshot line item survives menu deletion, only the back-reference clears). **Critical companion:** `BookingFoodItemFactory` switched from a random `Str::uuid()` (would violate the new FK on every insert) to `MenuItem::factory()`. Tests: delete-nulls-FK-keeps-snapshot + non-existent-id-rejected. BookingSeeder already FK-safe.

### Task E2: Cascade cluster (booking_seats.seat_id + loyalty_adjustments.user_id)
**Status:** ✅ Complete — 2026-06-05
- Both columns were NOT nullable, so made nullable + `nullOnDelete` (the audit's literal "nullOnDelete" couldn't apply as-is). `booking_seats.seat_id`: seat regeneration now nulls the reference instead of cascade-deleting the price/section snapshot. `loyalty_adjustments.user_id`: deleting a user no longer destroys the loyalty audit trail (mirrors the existing `admin_user_id` nullOnDelete). **Decision:** did NOT widen `getRegenerationBlockers` to count terminal bookings — nullOnDelete already preserves the snapshot, and blocking regeneration whenever any cancelled booking ever touched the auditorium would break the documented flow + seeder. Tests: seat-delete-nulls-snapshot, user-delete-keeps-audit.

### Task E3: promo_codes.is_active boolean → deactivated_at timestamp
**Status:** ✅ Complete — 2026-06-05
- Per the nullable-timestamp convention (free WHEN metadata). Migration: `boolean('is_active')` → `timestamp('deactivated_at')->nullable()` + composite index rename. Model: fillable/casts swap + a derived `is_active` Attribute accessor (`deactivated_at === null`) so every existing `$promo->is_active` reader (service validateCode/consume, Filament IconColumn + action visibility) keeps working untouched. Service `deactivate()` stamps `now()`. Filament: dropped the create-time `is_active` toggle (new codes active by default; deactivation is the audit-logged action). Factory: `is_active` default/`inactive()` → `deactivated_at`. Fixed the two raw `update(['is_active' => false])` call sites + the form `.set('data.is_active')` + four factory `create(['is_active' => true])` sites. New assertion: deactivate stamps `deactivated_at` (today).

`make fresh` re-ran all seeders cleanly against the new schema (validates FK/column changes end-to-end).

#### Batch E Files Changed
- Migrations (in place): `..._create_booking_food_items_table.php`, `..._create_booking_seats_table.php`, `..._create_loyalty_adjustments_table.php`, `..._create_promo_codes_table.php`.
- `backend/database/factories/{BookingFoodItemFactory,PromoCodeFactory}.php`; `backend/app/Models/PromoCode.php`; `backend/app/Services/PromoCodeService.php`; `backend/app/Filament/Resources/PromoCodeResource.php`.
- Tests: `Database/BookingFoodItemForeignKeyTest.php` (new), `Database/CascadeDeleteProtectionTest.php` (new), `Unit/Admin/PromoCodeServiceTest.php`, `Admin/Services/PromoCodeServiceIntegrationTest.php`, `Admin/Resources/PromoCodeResourceTest.php`, `Api/BookingControllerTest.php`, `Api/BookingHeldLifecycleTest.php`, `Admin/LoyaltyAdjustmentTest.php` (existing cascade test rewritten to the new preserve contract).

---

## Batch F — Missing payment-path security tests (no production change)

### Task F1: Non-3DS compensating-refund coverage
**Status:** ✅ Complete — 2026-06-05
- store() Phase C runs after capture; the compensating-refund path only had 3DS coverage. New `BookingCompensatingRefundTest`: a partial-gift-card booking (700 charged) with a container-stubbed `GiftCardService::redeemAgainstBooking` that throws → the generic `\Throwable` branch refunds the captured PI, discards the Held booking (Booking count 0), and writes no gift-card ledger entry. Asserts 500 + refund recorded.

### Task F2: Gift-card double-spend (HTTP layer)
**Status:** ✅ Complete — 2026-06-05
- New `GiftCardDoubleSpendTest` (race modeled as sequential same-card requests — single connection can't truly parallelize). (a) A card with exactly one seat's balance: booking #1 depletes it; booking #2 with the same depleted code is refused (400 giftCardCode); exactly one redemption ledger row, balance never negative. (b) A 1500 card: booking #1 redeems 1200 (balance 300); booking #2 sizes the redemption against the LIVE 300, charging the remaining 900 on the card (mixed, two ledger rows summing to -1500, balance 0).

#### Batch F Files Changed
- Tests only: `tests/Feature/Api/BookingCompensatingRefundTest.php` (new), `tests/Feature/Api/GiftCardDoubleSpendTest.php` (new).

---

## Batch G — Infra / CI hardening (config; manual/CI verification)

### Task G1: PHPStan gate (remove the swallow)
**Status:** ✅ Complete — 2026-06-05
- The `Run PHPStan` step keeps `|| true` so the SARIF/Code-Scanning upload always happens; added a **separate `Gate on PHPStan result` step** (after the upload, same `php_changed` condition) that re-runs `phpstan analyse` with a real exit code (a result-cache hit, so cheap) and fails the job on any error beyond baseline. **Caught a real one:** my C3 `$row['name'] ?? ''` introduced a `nullCoalesce.offset` error (the array shape guarantees `name`) — exactly what `|| true` would have silently swallowed. Fixed (`$row['name']`); PHPStan now clean, so the gate passes on the current tree.

### Task G2: Require DB/REDIS passwords on the prod-facing compose
**Status:** ✅ Complete — 2026-06-05
- `docker-compose.yml` (used in prod) baked `${DB_PASSWORD:-secret}`/`${REDIS_PASSWORD:-redissecret}` into all three backend services. Changed to `:?` (fail-loud) on all six client lines. Dev/local-prod still boot because the root `.env` supplies both vars (Compose auto-loads it). `docker-compose.stack.yml` server defaults left as dev conveniences. Verified: dev `config` renders; blanked prod-facing `config` errors with the `must be set` message.

### Task G3: Pin the :latest images
**Status:** ✅ Complete — 2026-06-05
- `crazymax/fail2ban:latest` → `1.1.0@sha256:a1476…` (version label confirmed; digest = the running image the jail config was verified against). `certbot/certbot:latest` → `@sha256:0107…` (digest-only; pulled to capture the current image). Both pin to exactly the running image → zero behavior change, full reproducibility.

### Task G4: Recycle the queue worker
**Status:** ✅ Complete — 2026-06-05
- `queue:work` (defined once in the base compose) gained `--max-time=3600 --max-jobs=1000 --memory=256 --backoff=5` so the long-lived worker recycles cleanly (Stripe SDK / Mailable / TMDB state reclaimed). Covers dev/prod/local-prod via the single base definition. Scheduler unchanged.

### Task G5: Mark the e2e APP_KEY as intentional
**Status:** ✅ Complete — 2026-06-05
- Added an inline comment to both `docker-compose.e2e.yml` APP_KEY lines marking the committed key as a fixed, intentionally-public test key (APP_ENV=testing, throwaway DB; never used where APP_ENV=production; CI may override). Kept the `:-` default so `make e2e` still boots without a hard prerequisite.

#### Batch G Files Changed
- `.github/workflows/backend-phpstan.yml` (gate step), `backend/app/Services/AuditoriumService.php` (PHPStan nullCoalesce fix), `docker-compose.yml` (password guards + image pin + queue flags), `docker-compose.prod.yml` (certbot pin), `docker-compose.e2e.yml` (APP_KEY comment).

---

## Summary

All 7 batches (A–G) of P1 remediation complete. **One finding refuted** (A1 — Filament 5.6 `->visible()` enforces server-side; the audit's premise was false; replaced with regression guards). **Two items intentionally deferred** (booking_seats partial-unique-index → own stacked PR; `per_user_limit` enforcement → v2; the field was hidden). Backend **1020 → 1052 tests**, frontend **885 → 892**, Pint clean, PHPStan clean (gate now real), all compose configs valid.
