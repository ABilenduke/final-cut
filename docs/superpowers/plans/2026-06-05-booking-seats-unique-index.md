# booking_seats partial-unique-index + Postgres triggers (status-derived occupies_seat) — DB-level TOCTOU backstop for seat double-booking

> **For agentic workers:** verified TDD dossier produced by the `design-three-hardening-features` workflow (8 mappers → 3 designers → 6 adversarial critics → 3 finalizers), empirically validated against the live `final_cut_test` Postgres. Steps are TDD: failing test first, minimal change, exact verify command.

**Goal:** DB-level backstop making seat double-booking impossible: a status-derived `occupies_seat` boolean on `booking_seats`, two Postgres triggers keeping it in sync across every (event-bypassing) write path, and a partial unique index — with the 23505 translated to `SeatConflictException` (409) by a constraint-specific, zero-DB-read catch.

**Tech Stack:** Laravel 13 (PHP 8.4, Pest) · PostgreSQL 18 · Nuxt 4 (Vitest) · Docker Compose · Filament 5

---


## Summary & verification notes

Add a denormalized `occupies_seat` boolean to `booking_seats`, two Postgres triggers that keep it in sync with the parent booking's status across every write path (including the Eloquent-event-bypassing `ShowtimeService::cancel` Builder mass-update), and a partial unique index `(showtime_id, seat_id) WHERE occupies_seat AND seat_id IS NOT NULL` that makes seat double-booking impossible at the database — mirroring how the `showtimes_no_overlap` EXCLUDE constraint backstops `detectConflicts()`. Then translate the index's 23505 violation inside `SeatAvailabilityService::reserveSeats` into the existing `SeatConflictException` (409), with a CONSTRAINT-SPECIFIC catch so it never mis-handles the `stripe_payment_intent_id`/`booking_id+seat_id`/`idempotency_key` unique races.

I verified EVERY load-bearing claim against real code and empirically against the live Postgres test DB (`final_cut_test`), settling the adversarial disputes with evidence rather than assertion:

EMPIRICALLY CONFIRMED (probe scripts, since removed):
1. A trigger-context partial-unique-index 23505 raised by a `BookingSeat::create`-style INSERT IS wrapped by Laravel as `Illuminate\Database\UniqueConstraintViolationException`, and `$e->index` is populated with the index NAME ('booking_seats_one_occupant_per_seat') and `$e->columns` with `['showtime_id','seat_id']`. (Laravel `PostgresConnection::isUniqueConstraintError` keys purely on SQLSTATE 23505; `parseUniqueConstraintViolation` regexes `unique constraint "<name>"` and `Key (...)=`.) → resolves Lens-1's broad-catch HIGH and Lens-1-low's "does trigger-context 23505 wrap?" doubt.
2. A SELECT after a 23505 inside an OPEN transaction throws SQLSTATE 25P02 ("current transaction is aborted"). → CONFIRMS Lens-2's BIGGEST-RISK: the original design's recompute-in-catch (`$this->checkAvailability()` inside the catch) is a real production bug that would mask `SeatConflictException` with a raw 500. FIX adopted: the catch does ZERO DB reads.
3. `confirmed→refund_pending` is a NO-OP under the `IS DISTINCT FROM` guard (seat stays occupied). → CONFIRMS Lens-2's "path D doesn't exercise trigger 2" — the trigger is belt-and-suspenders for `ShowtimeService::cancel`, not load-bearing. Reframed honestly.
4. FALSE→TRUE flip (un-cancel a booking onto a seat another booking already occupies) raises 23505 FROM INSIDE trigger 2's UPDATE, aborting the parent `bookings` UPDATE. → CONFIRMS Lens-1-medium/Lens-2-medium unhandled-500 risk is REAL. Adopted contract: documented intentional loud failure (no current app path reactivates a non-occupying→occupying status via Builder::update; the only reactivation is `CancellationFollowupQueue` Model::update which fires events and goes refund_pending→refunded, never INTO an occupied seat). Pinned by a test.
5. `fc_status_occupies(NULL)` with `COALESCE(p,'')` returns FALSE (not NULL); `boolean()->default(false)` is NOT NULL (is_nullable=NO). → resolves Lens-2-medium silent-NULL-bypass: `occupies_seat` is always a well-defined boolean, never NULL.

CODE FACTS CORRECTED vs the design as written:
- There is NO `StripeHelper`/`AuthHelper` trait (CLAUDE.md is aspirational). The real helper is `Tests\Helpers\BookingTestHelper` (`uses(BookingTestHelper::class)`) with `$this->fakeStripe()` (binds `FakeStripeService`), `$this->createShowtimeWithSeats()` (returns `['location','auditorium','seats','showtime','movie']`, seats A1/A2/A3 standard, B1 premium, C1 accessible), `$this->bookingUrl($location)` / `$this->bookingUrl($location,'confirm')`. The fake supports `shouldRequire3ds()` / `shouldSucceed()`. The store route REQUIRES an `Idempotency-Key` header in practice and payment field is `paymentMethodId` (e.g. `pm_test_visa`). `Location::getRouteKeyName()` returns `slug`.
- CreateBookingRequest fields: `showtimeId`, `seatIds[]`, `foodItems`, `paymentMethodId` (`required_without:giftCardCode`), `email`. confirm() body: `paymentIntentId`. confirm() success returns `data.paymentIntentId` at requires_action.
- Seeder is SAFE today: `BookingSeeder` creates only `Confirmed`+`Cancelled` bookings; the Cancelled one does NOT call `markSeatsUsed` (line 222-224) and is last in its showtime pool, so no two OCCUPYING bookings ever share a seat. `preloadOccupiedSeats` dedups on Confirmed only (line 280) — harmless today but I recommend aligning it to `occupyingStatuses()` for future-proofing.

RESULT: All 3 Lens-1 HIGH, all 3 Lens-2 HIGH, and every medium/low are folded in (incorporated or evidence-rebutted). The single rejected-but-airtight item: the dead `OR UPDATE OF booking_id` clause (Lens-1-high #3) — KEPT defensively but documented as currently-dead, with the intra-trigger TOCTOU precondition (booking_seats always inserted in the same txn as, and after, the parent booking, under the showtime lockForUpdate) documented in the migration; design stays airtight because no path inserts booking_seats for a pre-existing booking.

---

## Files

- **[create]** `backend/database/migrations/2026_06_05_000000_add_booking_seats_occupancy_guard.php` — ADDITIVE migration (the one documented exception). Adds occupies_seat boolean NOT NULL default false; fc_status_occupies(text) IMMUTABLE helper with COALESCE; fc_booking_seat_set_occupies() BEFORE INSERT/UPDATE OF booking_id trigger; fc_booking_resync_seat_occupies() AFTER UPDATE OF status trigger with IS DISTINCT FROM guard; partial unique index booking_seats_one_occupant_per_seat. Each CREATE TRIGGER is preceded by DROP TRIGGER IF EXISTS for re-runnability; full teardown in down().
- **[modify]** `backend/app/Services/SeatAvailabilityService.php` — Add `use Illuminate\Database\UniqueConstraintViolationException;` AND wrap the BookingSeat::create loop in try/catch IN THE SAME EDIT (Pint gotcha). Catch branches on $e->index === 'booking_seats_one_occupant_per_seat'; if matched, throw new SeatConflictException($e->columns-derived ids OR $seatIds) with NO DB read; otherwise re-throw (so booking_id+seat_id and stripe PI/idempotency unique races are never mis-translated).
- **[modify]** `backend/app/Enums/BookingStatus.php` — Append a DB-coupling cross-reference to the occupyingStatuses() docblock pointing at fc_status_occupies() in migration 2026_06_05_000000, stating the two lists must change together.
- **[create]** `backend/tests/Feature/Booking/SeatOccupancyTriggerTest.php` — Schema/trigger-level Pest tests (RefreshDatabase, no HTTP): occupies_seat derivation on insert for all 5 statuses; Builder::update status flips re-sync occupies_seat both directions; partial index rejects a 2nd occupying row (savepoint-wrapped, assert 23505); cancelled-freed seat re-bookable; NULL seat_id rows never collide; confirmed→refund_pending is a NO-OP (guard); FALSE→TRUE un-cancel into occupied seat raises 23505 (documented loud failure); orphan/NULL-status parent yields occupies_seat=false; PHP↔SQL parity (fc_status_occupies(status) vs in_array(...,occupyingStatuses)) for all 5 cases; seat-regeneration (force) nulls seat_id, row survives with occupies_seat unchanged, no index breakage, seat re-bookable.
- **[create]** `backend/tests/Feature/Booking/SeatReservationConcurrencyTest.php` — Service-level Pest tests: (1) regression lock — an existing occupying booking_seats row makes reserveSeats throw SeatConflictException via the checkAvailability pre-check (loser writes zero seats). (2) translation proof — partial-mock checkAvailability()->andReturn([]) so the INDEX (not the pre-check) trips; assert reserveSeats re-throws SeatConflictException. (3) MANDATORY new test from Lens-2: run reserveSeats INSIDE an outer DB::transaction (mirroring Phase A/C) with checkAvailability stubbed to []; assert the result is a clean SeatConflictException, NOT a 25P02 — proving the catch does no DB read after the 23505 poisons the txn. (4) negative — a booking_id+seat_id unique violation (different constraint) is re-thrown as UniqueConstraintViolationException, NOT mis-translated to SeatConflictException.
- **[create]** `backend/tests/Feature/Api/SeatDoubleBookTest.php` — HTTP end-to-end Pest test using BookingTestHelper: two sequential POST /api/locations/{slug}/bookings for the same seat — first 201, second 409 with errors.0.unavailableSeatIds.0 == seat id. Explicit comment that this proves the PRE-CHECK 409 (the committed first booking is seen by the second's checkAvailability); the index backstop itself is covered by the trigger test + the unit translation test. PLUS the 3DS seat-race test (Lens-2 missing test): store() with shouldRequire3ds() discards the Held booking and returns paymentIntentId; seed a conflicting occupying booking on the seat; shouldSucceed() then POST confirm — assert 409 (SeatConflictException via index→catch→\Throwable@643), exactly one refundPaymentIntent recorded on the fake, and zero Confirmed bookings for that PI.

---

## Tasks

### T1: Migration: occupies_seat column + COALESCE helper + 2 triggers + partial unique index (TDD)

**Test scenarios:**
- occupies_seat=true on insert when parent booking is confirmed | held | refund_pending (3 dataset rows)
- occupies_seat=false on insert when parent is cancelled | refunded (2 dataset rows)
- Builder::update bookings.status confirmed→refunded flips occupies_seat true→false (event-bypassing path)
- Builder::update bookings.status cancelled→confirmed flips occupies_seat false→true
- Partial index rejects a 2nd occupying booking_seats row for same (showtime,seat) → SQLSTATE 23505, count stays 1
- Seat freed by a cancelled booking is re-bookable by a new confirmed booking (index must NOT block)
- Multiple occupying rows with seat_id=NULL coexist (Postgres NULLs distinct + WHERE seat_id IS NOT NULL)
- confirmed→refund_pending is a NO-OP: zero booking_seats writes, occupies_seat stays true (IS DISTINCT FROM guard)
- FALSE→TRUE un-cancel into an already-occupied seat raises UniqueConstraintViolationException/23505 from inside trigger 2 (documented intentional loud failure)
- Orphan booking_seats row (non-existent booking_id) gets occupies_seat=false via COALESCE, never NULL
- PHP↔SQL parity: SELECT fc_status_occupies(status) matches in_array(case, BookingStatus::occupyingStatuses()) for all 5 cases
- Seat regeneration with force=true nulls seat_id on an occupying row: row survives with occupies_seat unchanged (true), exits the index, seat is re-bookable, not a phantom index occupant

**Implementation:**

NEW file backend/database/migrations/2026_06_05_000000_add_booking_seats_occupancy_guard.php (timestamp AFTER the latest existing 2026_05_07_120000 so it runs last; booking_seats exists from 2026_04_04_200010 — confirmed). Mirror the raw-SQL DB::statement idiom of 2026_04_24_000000 (import Illuminate\Support\Facades\DB; NOWDOC heredoc <<<'SQL'). Use Schema only for the column add.

up():
1) Schema::table('booking_seats', fn($t) => $t->boolean('occupies_seat')->default(false)->after('price')). VERIFIED: this is NOT NULL (information_schema is_nullable=NO).
2) fc_status_occupies(text) helper — CRITICAL: wrap in COALESCE so a missing/NULL parent status yields FALSE deterministically (VERIFIED returns false), keeping the NOT NULL column always well-defined:
   CREATE OR REPLACE FUNCTION fc_status_occupies(p_status text) RETURNS boolean LANGUAGE sql IMMUTABLE AS $$ SELECT COALESCE(p_status,'') IN ('confirmed','held','refund_pending') $$
   Docblock: 'Keep in sync with App\Enums\BookingStatus::occupyingStatuses().'
3) Trigger 1 fn fc_booking_seat_set_occupies() (plpgsql): SELECT status INTO v_status FROM bookings WHERE id = NEW.booking_id; NEW.occupies_seat := fc_status_occupies(v_status); RETURN NEW. Then DROP TRIGGER IF EXISTS trg_booking_seat_set_occupies ON booking_seats; CREATE TRIGGER trg_booking_seat_set_occupies BEFORE INSERT OR UPDATE OF booking_id ON booking_seats FOR EACH ROW EXECUTE FUNCTION fc_booking_seat_set_occupies(). COMMENT (Lens-1-high #3, rejected-but-documented): 'OR UPDATE OF booking_id is currently DEAD (no app path updates booking_seats.booking_id) — kept defensively. PRECONDITION: booking_seats is always inserted in the SAME transaction as, and AFTER, its parent booking row, while the showtime row is held under lockForUpdate (SeatAvailabilityService::reserveSeats docblock). The unlocked SELECT status is therefore txn-stable. If any future path inserts booking_seats for a pre-existing booking, this SELECT must FOR SHARE-lock the parent.'
4) Trigger 2 fn fc_booking_resync_seat_occupies() (plpgsql): IF fc_status_occupies(NEW.status) IS DISTINCT FROM fc_status_occupies(OLD.status) THEN UPDATE booking_seats SET occupies_seat = fc_status_occupies(NEW.status) WHERE booking_id = NEW.id; END IF; RETURN NEW. Then DROP TRIGGER IF EXISTS trg_booking_resync_seat_occupies ON bookings; CREATE TRIGGER trg_booking_resync_seat_occupies AFTER UPDATE OF status ON bookings FOR EACH ROW EXECUTE FUNCTION fc_booking_resync_seat_occupies(). COMMENT (Lens-2 reframing): 'The IS DISTINCT FROM guard makes confirmed↔held and confirmed→refund_pending NO-OPs (both occupy) — VERIFIED. The ShowtimeService::cancel Builder mass-update (confirmed→refund_pending) is therefore a no-op here; this trigger is BELT-AND-SUSPENDERS for that path, not load-bearing. Its real guarantee: occupies_seat stays correct under ANY event-bypassing status write, present or future. A reactivation that flips non-occupying→occupying onto an already-occupied seat will raise 23505 from this UPDATE and abort the parent bookings UPDATE — an intentional loud failure (you cannot un-cancel onto a seat someone else now holds). No app path does this today.'
5) Partial unique index: CREATE UNIQUE INDEX booking_seats_one_occupant_per_seat ON booking_seats (showtime_id, seat_id) WHERE occupies_seat AND seat_id IS NOT NULL. COMMENT: 'Authoritative TOCTOU backstop behind SeatAvailabilityService::reserveSeats lockForUpdate. Plain btree — no btree_gist needed (that extension is only for the showtimes GiST EXCLUDE). seat_id IS NOT NULL excludes regeneration-orphaned snapshot rows. occupies_seat alone is NOT the guard predicate — occupies_seat AND seat_id IS NOT NULL is.'

down(): DROP INDEX IF EXISTS booking_seats_one_occupant_per_seat; DROP TRIGGER IF EXISTS trg_booking_resync_seat_occupies ON bookings; DROP TRIGGER IF EXISTS trg_booking_seat_set_occupies ON booking_seats; DROP FUNCTION IF EXISTS fc_booking_resync_seat_occupies(); DROP FUNCTION IF EXISTS fc_booking_seat_set_occupies(); DROP FUNCTION IF EXISTS fc_status_occupies(text); Schema::table drop occupies_seat. (CREATE TRIGGER is now re-runnable thanks to the DROP IF EXISTS pairs — honors the idempotency claim, fixing Lens-2-low.)

Then edit BookingStatus.php occupyingStatuses() docblock to add the fc_status_occupies coupling note.

TESTS (write FIRST): backend/tests/Feature/Booking/SeatOccupancyTriggerTest.php, RefreshDatabase. beforeEach: Auditorium::factory, Showtime::factory(['auditorium_id'=>...]), Seat::factory(['auditorium_id'=>...]). Use Booking::factory(['showtime_id'=>..,'status'=>..]) + BookingSeat::factory(['booking_id'=>..,'showtime_id'=>..,'seat_id'=>..]) (BookingSeatFactory.seat_id defaults to a fresh Seat — always pass explicit seat_id or null). Read occupies_seat via DB::table('booking_seats')->where('id',$bs->id)->value('occupies_seat') cast to bool. For the index-rejection + un-cancel-collision tests, wrap the offending write in an INNER DB::transaction savepoint (mirror ShowtimeConflictConcurrencyTest.php:54-79) and catch QueryException, asserting errorInfo[0]==='23505' — otherwise the RefreshDatabase outer txn aborts (25P02) and follow-up reads break. PHP↔SQL parity test: foreach BookingStatus::cases() assert (bool) DB::scalar('select fc_status_occupies(?)',[$case->value]) === in_array($case, BookingStatus::occupyingStatuses(), true). Regeneration test: create occupying booking + booking_seats, then app(AuditoriumService::class)->generateSeats($auditorium, $config, force: true) (or delete the seat directly to trigger nullOnDelete cascade), assert the booking_seats row survives with seat_id NULL and occupies_seat still true, and that a new occupying booking can take a freshly-regenerated seat.

**Verify:** `make fresh && docker compose exec -u 1000 backend php artisan test --filter=SeatOccupancyTriggerTest → all green. Spot-check schema: docker compose exec -u 1000 -e DB_DATABASE=final_cut_test backend php artisan db --execute='\\d booking_seats' shows occupies_seat boolean not null + index booking_seats_one_occupant_per_seat.`

### T2: Translate the partial-index 23505 in reserveSeats → SeatConflictException, CONSTRAINT-SPECIFIC, NO DB read in catch (TDD)

**Test scenarios:**
- Regression: an existing occupying booking_seats row → reserveSeats throws SeatConflictException via the checkAvailability pre-check; loser writes zero booking_seats
- Translation: partial-mock checkAvailability()->andReturn([]) so the INDEX (not the pre-check) trips 23505 → reserveSeats re-throws SeatConflictException
- MANDATORY (Lens-2): reserveSeats called INSIDE an outer DB::transaction (mirroring Phase A/C) with checkAvailability stubbed [] → result is a clean SeatConflictException, NOT a 25P02 QueryException (proves the catch does no DB read after the abort)
- Negative: a booking_id+seat_id unique violation (duplicate seat for SAME booking) is re-thrown as UniqueConstraintViolationException, NOT mis-translated (proves $e->index branching)

**Implementation:**

Edit backend/app/Services/SeatAvailabilityService.php. SINGLE EDIT (Pint gotcha): add `use Illuminate\Database\UniqueConstraintViolationException;` to the imports AND wrap the BookingSeat::create loop together.

Wrap the foreach ($seatIds as $seatId) { ... BookingSeat::create(...) ... } loop in try/catch:

  try {
      foreach ($seatIds as $seatId) { ...existing body... }
  } catch (UniqueConstraintViolationException $e) {
      // The partial UNIQUE INDEX booking_seats_one_occupant_per_seat (migration
      // 2026_06_05_000000) is the authoritative TOCTOU backstop behind the
      // caller's lockForUpdate(showtime). A racing booking grabbed a seat in
      // the gap between checkAvailability() above and this INSERT.
      //
      // CONSTRAINT-SPECIFIC (adversarial Lens-1 HIGH): ONLY translate the seat
      // occupancy index. The other unique constraints reachable here
      // (booking_seats unique(booking_id,seat_id); and in confirm() the
      // bookings unique stripe_payment_intent_id / idempotency_key handled at
      // BookingController:611/238) MUST keep their own meaning, so re-throw
      // anything else. $e->index is populated by Laravel's
      // PostgresConnection::parseUniqueConstraintViolation (VERIFIED: yields
      // 'booking_seats_one_occupant_per_seat').
      if ($e->index !== 'booking_seats_one_occupant_per_seat') {
          throw $e;
      }
      // NO DB READ HERE (adversarial Lens-2 BIGGEST RISK, VERIFIED 25P02): the
      // 23505 has aborted the caller's open transaction; any SELECT now throws
      // 'transaction is aborted'. We cannot recompute checkAvailability. Report
      // the requested seats; the client re-selects. ($e->columns carries the
      // showtime_id+seat_id VALUES, not the losing seat id alone, so it can't
      // pinpoint the loser without a read — reporting $seatIds is correct and
      // safe.)
      throw new SeatConflictException($seatIds);
  }

WHY SeatConflictException (a RuntimeException, NOT a UniqueConstraintViolationException — VERIFIED hierarchy) matters in confirm() Phase C: reserveSeats runs inside the line-547 transaction. If the index 23505 escaped untranslated, it would hit the line-611 `catch (UniqueConstraintViolationException)` which assumes the stripe_payment_intent_id race, look up a non-existent PI booking, and re-throw → unhandled 500 with NO refund. By re-throwing SeatConflictException instead, it skips line 611 and lands at the line-643 `\Throwable` catch → refundOrReport + re-throw → global 409. In Phase A (store) it propagates out of the line-141 transaction (rolling back any half-written seats) to the controller boundary → global 409.

TESTS (write FIRST): backend/tests/Feature/Booking/SeatReservationConcurrencyTest.php. (1) regression: seed occupying winner, expect(fn()=>$service->reserveSeats($showtime,[$seat->id],$loser))->toThrow(SeatConflictException::class); assert BookingSeat::where('booking_id',$loser->id)->count()===0. (2)+(3) translation: $service = Mockery::mock(SeatAvailabilityService::class)->makePartial(); $service->shouldReceive('checkAvailability')->andReturn([]); seed the winner row; for (3) call inside DB::transaction(fn()=>$service->reserveSeats(...)) to mirror Phase A/C and assert the thrown class is SeatConflictException (would be QueryException/25P02 if the catch did a recompute). (4) negative: create a booking + a booking_seat for ($booking,$seat); then attempt a second BookingSeat::create for the SAME ($booking,$seat) via reserveSeats-adjacent path or a direct create wrapped in a savepoint, asserting UniqueConstraintViolationException with $e->index === 'booking_seats_booking_id_seat_id_unique' is re-thrown (NOT SeatConflictException) — confirm the exact index name from \d booking_seats first.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=SeatReservationConcurrencyTest → green. docker compose exec -u 1000 backend ./vendor/bin/pint --dirty → clean (confirms the import wasn't stripped). docker compose exec -u 1000 backend php -d memory_limit=512M vendor/bin/phpstan analyse → no new errors (no env() used).`

### T3: HTTP double-book regression + 3DS seat-race-during-confirm refund test (TDD)

**Test scenarios:**
- Two sequential POST /api/locations/{slug}/bookings for the same seat: first 201, second 409 with errors.0.unavailableSeatIds.0 == seat id (proves the pre-check 409 end-to-end)
- 3DS path: store() with shouldRequire3ds() returns requiresAction + paymentIntentId and discards the Held booking; seed a conflicting occupying booking on the seat; shouldSucceed() then POST confirm → 409, exactly ONE refundPaymentIntent on the fake, ZERO Confirmed bookings for that PI (proves index→catch→\Throwable@643→refundOrReport, no orphaned Confirmed booking, no double-charge)

**Implementation:**

NEW file backend/tests/Feature/Api/SeatDoubleBookTest.php. uses(BookingTestHelper::class) (there is NO StripeHelper/AuthHelper trait — corrected). beforeEach: $this->fakeStripe(); $this->fixture = $this->createShowtimeWithSeats(['start_time'=>now()->addDay()]); pick $seat = $this->fixture['seats'][0].

Test 1 (sequential 409): $payload = ['showtimeId'=>$fixture['showtime']->id,'seatIds'=>[$seat->id],'paymentMethodId'=>'pm_test_visa','email'=>'guest@example.com']. First: postJson($this->bookingUrl($fixture['location']), $payload, ['Idempotency-Key'=>(string)Str::uuid()])->assertStatus(201). Second (distinct Idempotency-Key so it's a real second attempt, not an idempotent replay): postJson(same url, $payload, ['Idempotency-Key'=>(string)Str::uuid()])->assertStatus(409)->assertJsonPath('errors.0.unavailableSeatIds.0', $seat->id). COMMENT: 'This proves the checkAvailability PRE-CHECK returns 409 — the committed first booking is seen by the second request. The partial INDEX backstop itself is covered by SeatOccupancyTriggerTest (DB level) + SeatReservationConcurrencyTest (catch translation). Under single-connection RefreshDatabase the index cannot fire here because the pre-check sees the same committed rows.'

Test 2 (3DS seat race → refund, Lens-2 mandatory missing test): $fake = $this->fakeStripe(); $fake->shouldRequire3ds(); $r = postJson($this->bookingUrl($fixture['location']), $payload, ['Idempotency-Key'=>(string)Str::uuid()]); $r->assertOk(); $pi = $r->json('data.paymentIntentId'); // Held booking was discarded at requires_action (BookingController:325), seat is free. Now simulate a racing customer grabbing the seat during the 3DS wait: $other = Booking::factory()->create(['showtime_id'=>$fixture['showtime']->id,'status'=>BookingStatus::Confirmed]); BookingSeat::factory()->create(['booking_id'=>$other->id,'showtime_id'=>$fixture['showtime']->id,'seat_id'=>$seat->id]); // confirm: $fake->shouldSucceed(); $c = postJson($this->bookingUrl($fixture['location'],'confirm'), ['paymentIntentId'=>$pi]); $c->assertStatus(409); // assert single refund and no orphaned Confirmed booking for this PI: expect($fake->refundedPaymentIntents)->toHaveCount(1); expect($fake->refundedPaymentIntents[0]['paymentIntentId'])->toBe($pi); expect(Booking::where('stripe_payment_intent_id',$pi)->where('status',BookingStatus::Confirmed)->count())->toBe(0). NOTE: in this single-connection harness the racing row is committed at baseline before confirm(), so confirm()'s checkAvailability pre-check (NOT the index) will detect it and throw SeatConflictException — which still flows through the same \Throwable@643 → refundOrReport path, so the refund+no-orphan assertions hold regardless of whether the pre-check or the index fires. Document this in a comment.

No production code change beyond T1+T2 — these are regression locks.

**Verify:** `docker compose exec -u 1000 backend php artisan test --filter=SeatDoubleBookTest → green. If the first assertCreated() fails on validation, re-confirm field names against CreateBookingRequest (showtimeId/seatIds/paymentMethodId/email) and that the Idempotency-Key header is present.`

### T4: Full-suite regression + seeder/make-fresh survival + style/static gates

**Test scenarios:**
- make fresh succeeds (seeders do not collide with the new partial index)
- Existing booking suites stay green under the now-firing triggers (BookingControllerTest, BookingHeldLifecycleTest, BookingIdempotencyTest, BookingCompensatingRefundTest, BookingStripeOutsideTransactionTest)
- ShowtimeService cancel/refund suites stay green (trigger 2 fires on their status writes)
- Pint + PHPStan gates clean

**Implementation:**

No new code unless a regression surfaces. Verified pre-emptively: BookingSeeder creates only Confirmed+Cancelled bookings; the Cancelled one (future index 2) does NOT call markSeatsUsed (line 222-224) and is the last booking in its showtime pool, so no two OCCUPYING bookings ever share (showtime,seat) — make fresh survives. RECOMMENDED non-blocking hardening: change BookingSeeder::preloadOccupiedSeats (line 280) from ->where('bookings.status', BookingStatus::Confirmed) to ->whereIn('bookings.status', array_map(fn($s)=>$s->value, BookingStatus::occupyingStatuses())) so it matches the index predicate if a future seeder adds Held/RefundPending rows. If make fresh ever fails with 23505 from a seeder, the offending seeder is creating two occupying bookings on the same seat — give them distinct seats. If any existing test seeds two occupying bookings on one seat and now hits 23505, give those bookings distinct seats (the test was relying on previously-unenforced behavior).

**Verify:** `make fresh && docker compose exec -u 1000 backend php artisan optimize:clear && docker compose exec -u 1000 backend php artisan test --filter='SeatOccupancyTriggerTest|SeatReservationConcurrencyTest|SeatDoubleBookTest' && make test-backend && docker compose exec -u 1000 backend ./vendor/bin/pint --dirty && docker compose exec -u 1000 backend php -d memory_limit=512M vendor/bin/phpstan analyse → all green, full suite passes, style + static clean.`

---

## Gotchas

- EMPIRICALLY VERIFIED — recompute-in-catch is a real bug: a SELECT after a 23505 inside the still-open caller transaction throws SQLSTATE 25P02 ('current transaction is aborted'). The reserveSeats catch MUST do ZERO DB reads — throw new SeatConflictException($seatIds) directly. The original design's `$recomputed = $this->checkAvailability(...)` is dead code that would mask the 409 with a 500.
- EMPIRICALLY VERIFIED — constraint-specific catch is possible: a trigger-context partial-index 23505 from BookingSeat::create wraps as Illuminate\Database\UniqueConstraintViolationException with $e->index === 'booking_seats_one_occupant_per_seat' and $e->columns === ['showtime_id','seat_id'] (Laravel PostgresConnection regexes the PG message). Branch on $e->index; re-throw everything else so the booking_id+seat_id unique and the bookings stripe_payment_intent_id/idempotency_key uniques keep their own handling at BookingController:611/238.
- EMPIRICALLY VERIFIED — confirm() Phase C ordering: reserveSeats (line 597) runs inside the line-547 transaction. SeatConflictException extends RuntimeException (NOT UniqueConstraintViolationException), so re-throwing it correctly BYPASSES the line-611 stripe_payment_intent_id catch and lands at the line-643 \Throwable catch → refundOrReport($pi) + re-throw → 409. If you ever throw a UniqueConstraintViolationException out of reserveSeats instead, line 611 mis-handles it as a PI race → unhandled 500 with NO refund.
- EMPIRICALLY VERIFIED — FALSE→TRUE collision inside trigger 2: un-cancelling a booking (cancelled→confirmed via Builder::update) onto a seat another booking already occupies raises 23505 FROM INSIDE trigger 2's UPDATE, aborting the parent bookings UPDATE. This is an intentional loud failure (documented + tested). No current app path does this: ShowtimeService::cancel only goes →refund_pending; CancellationFollowupQueue goes refund_pending→refunded (non-occupying). So no admin-site catch is needed today, but it is pinned by a test so it's a known failure, not a latent 500.
- EMPIRICALLY VERIFIED — IS DISTINCT FROM guard makes confirmed→refund_pending a NO-OP. ShowtimeService::cancel's event-bypassing Builder mass-update (confirmed→refund_pending, both occupy) does NOT actually exercise trigger 2's body — the trigger is belt-and-suspenders for that path, not load-bearing. State this honestly; do not claim the trigger 'covers path D's occupancy semantics'.
- EMPIRICALLY VERIFIED — NULL safety: fc_status_occupies MUST use COALESCE(p_status,'') so a missing/NULL parent status yields FALSE (not NULL). boolean()->default(false) is NOT NULL (is_nullable=NO). Without COALESCE, an orphan/NULL-status lookup would make NEW.occupies_seat := NULL and raise a NOT-NULL violation at insert. With COALESCE it's always a well-defined boolean and the partial index is never silently bypassed.
- HELPER NAMES CORRECTED: there is NO StripeHelper or AuthHelper trait (CLAUDE.md is aspirational). Use uses(BookingTestHelper::class) → $this->fakeStripe() (binds FakeStripeService), $this->createShowtimeWithSeats() (returns ['location','auditorium','seats','showtime','movie']), $this->bookingUrl($location) / $this->bookingUrl($location,'confirm'). The fake supports shouldRequire3ds()/shouldSucceed()/shouldDecline() and records ->refundedPaymentIntents/->confirmedPaymentIntents. The store route needs an Idempotency-Key header; payment field is paymentMethodId='pm_test_visa'. Location route key is slug.
- Pint strips a 'use' import added in a separate edit from its first usage — add the UniqueConstraintViolationException import and the catch block in ONE edit to SeatAvailabilityService.php.
- RefreshDatabase = single connection: you cannot open real parallel transactions. Index-rejection and un-cancel-collision tests must wrap the offending write in an INNER DB::transaction savepoint (mirror ShowtimeConflictConcurrencyTest.php:54-79) so the 23505 abort doesn't poison the outer RefreshDatabase txn (25P02). The translation unit test uses a partial Mockery mock of checkAvailability()->andReturn([]) to force the INDEX (not the pre-check) to fire.
- Migration is the ONE documented ADDITIVE exception — NEW file timestamped after 2026_05_07_120000 (the current latest), NOT an edit to 2026_04_24_000000. 'Co-locate' is conceptual (mirror its raw-SQL DB::statement idiom). Run `make fresh` after, then `php artisan optimize:clear` before tests (stale route cache → 404s).
- btree_gist is NOT needed — a plain btree partial-unique index requires no extension (btree_gist is only for the showtimes GiST EXCLUDE). Do not touch CREATE EXTENSION.
- Idempotency: each CREATE TRIGGER is preceded by DROP TRIGGER IF EXISTS so the migration is re-runnable (fixing the original design's asymmetric idempotency where only down() was guarded). down() drops index, both triggers, all three functions, then the column — in dependency order.

## Test matrix

- TRIGGER 1 insert: occupies_seat=true for parent status confirmed (dataset row)
- TRIGGER 1 insert: occupies_seat=true for parent status held (dataset row)
- TRIGGER 1 insert: occupies_seat=true for parent status refund_pending (dataset row)
- TRIGGER 1 insert: occupies_seat=false for parent status cancelled (dataset row)
- TRIGGER 1 insert: occupies_seat=false for parent status refunded (dataset row)
- TRIGGER 2 Builder::update confirmed→refunded flips occupies_seat true→false (event-bypassing path)
- TRIGGER 2 Builder::update cancelled→confirmed flips occupies_seat false→true
- TRIGGER 2 confirmed→refund_pending is a NO-OP: occupies_seat stays true, zero booking_seats writes (IS DISTINCT FROM guard)
- INDEX rejects a 2nd occupying booking_seats row for same (showtime,seat): SQLSTATE 23505 (savepoint-wrapped), surviving count == 1
- INDEX allows re-booking a seat freed by a cancelled booking (no throw, occupies_seat=true on the new row)
- INDEX ignores NULL seat_id: two occupying rows with seat_id=null coexist (count == 2)
- COLLISION FALSE→TRUE: un-cancel (cancelled→confirmed) onto an already-occupied seat raises UniqueConstraintViolationException/23505 from inside trigger 2 (documented intentional loud failure)
- NULL/orphan parent: booking_seats row with non-existent booking_id gets occupies_seat=false via COALESCE (never NULL)
- PHP↔SQL parity: fc_status_occupies(status) == in_array(case, occupyingStatuses()) for all 5 BookingStatus cases (fails CI on drift)
- REGENERATION: force=true seat delete nulls seat_id on an occupying row — row survives with occupies_seat unchanged (true), exits the unique index, seat is re-bookable, not a phantom occupant
- SERVICE regression: existing occupying row → reserveSeats throws SeatConflictException via checkAvailability pre-check; loser writes zero booking_seats
- SERVICE translation: partial-mock checkAvailability()->andReturn([]) forces the INDEX 23505 → reserveSeats re-throws SeatConflictException
- SERVICE no-poison: reserveSeats inside an outer DB::transaction (Phase A/C mirror) with checkAvailability stubbed [] → clean SeatConflictException, NOT a 25P02 QueryException
- SERVICE negative: a booking_id+seat_id unique violation ($e->index != occupancy index) is re-thrown as UniqueConstraintViolationException, NOT mis-translated to SeatConflictException
- HTTP sequential: 1st POST /bookings 201, 2nd POST (distinct Idempotency-Key) 409 with errors.0.unavailableSeatIds.0 == seat id
- HTTP 3DS seat race: shouldRequire3ds store → requiresAction+paymentIntentId (Held discarded); seed racing occupying booking on the seat; shouldSucceed confirm → 409, exactly one refundPaymentIntent for that PI, zero Confirmed bookings for that PI
- SUITE: make fresh seeds without colliding with the new index; full make test-backend green (no existing booking/showtime test regressed under the now-firing triggers)

## Open risks

- The FALSE→TRUE un-cancel collision is handled as an intentional loud 500-class failure (UniqueConstraintViolationException bubbling from trigger 2), NOT a clean domain error, because no current app path triggers it. If a future admin feature lets staff reactivate a cancelled/refunded booking via Builder::update (or Model::update onto an occupied seat), it will surface as an unhandled 500. Mitigation when that feature lands: wrap the admin status-flip site in a catch translating $e->index === 'booking_seats_one_occupant_per_seat' → a domain 'seat already reassigned' error. The pinning test documents the current contract so the regression is loud, not silent.
- occupies_seat is a status-derived denormalization, NOT regeneration-aware. After a force=true regeneration nulls seat_id on an occupying booking_seats row, that row keeps occupies_seat=true but exits the index (WHERE seat_id IS NOT NULL). It is harmless for uniqueness and the existing AuditoriumService::getRegenerationBlockers remains the authoritative regeneration guard (it inner-joins booking_seats→seats, so NULL-seat rows correctly don't block). Risk only materializes if a future query trusts occupies_seat ALONE (without AND seat_id IS NOT NULL) as authoritative occupancy — documented in the index comment.
- The intra-trigger-1 SELECT status FROM bookings is unlocked. It is safe today only because reserveSeats always inserts booking_seats in the SAME transaction as, and after, the parent booking, under the showtime lockForUpdate (documented precondition). If any future caller inserts booking_seats for a pre-existing booking under READ COMMITTED, a concurrent status flip could be read stale. Mitigation noted in the migration comment: add FOR SHARE to the SELECT if that pattern ever appears.
- The dead `OR UPDATE OF booking_id` clause on trigger 1 is kept defensively (no app path updates booking_seats.booking_id today). It is harmless but signals a guarded-but-unused axis; documented as such. If verified permanently unreachable, it can be dropped in a follow-up.
- BookingSeeder::preloadOccupiedSeats dedups on Confirmed only. Safe today (no Held/RefundPending seeded), but if a future seeder adds occupying-but-not-confirmed bookings sharing a seat with a Confirmed one, make fresh will fail with 23505. Recommended (non-blocking) hardening: align the dedup to BookingStatus::occupyingStatuses(). Flagged, not applied, to keep this change narrow.
