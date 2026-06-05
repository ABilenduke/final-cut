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
