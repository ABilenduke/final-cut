# Plan 05 — Walk-up / POS booking creation

## Goal

Box-office staff sell tickets at the counter: pick an upcoming showtime, click seats on the
occupancy grid, record how payment was taken, done. Closes the audit's "all bookings
originate from the customer API" gap.

## Scope guard (v1)

Cash / comp / card-taken-on-the-physical-terminal only — **no Stripe Terminal integration,
no food items, no promo or gift-card redemption**. Those stay in the customer checkout.
`PaymentMethod` gains `Cash`, `Comp`, `PosCard` cases (string column; customer API
enum-matches nowhere exhaustively, verified).

## Design

- **`WalkUpBookingService::create(showtimeId, seatIds, paymentMethod, guestEmail, actor,
  notes)`** — single transaction mirroring checkout Phase A: `lockForUpdate` the
  non-cancelled showtime, refuse past showtimes (`WalkUpBookingException`), create the
  booking **straight to Confirmed** (no Stripe wait → no Held phase needed; the lock is held
  throughout), then `SeatAvailabilityService::reserveSeats()` (which owns section-multiplier
  pricing, foreign-seat validation, and the occupancy-guard TOCTOU translation to
  `SeatConflictException`). Money semantics: cash/pos_card → `total = seatTotal`; **comp →
  `discount = seatTotal`, `total = 0`** so revenue KPIs stay honest. Only POS payment
  methods are accepted (`not_pos_method` guard). Actor is REQUIRED (this is a staff action);
  activity event `booking.walkup_created`.
- **`CreateWalkUpBooking` page** (`/walk-up` under BookingResource, gated by new
  `bookings.create_walkup`): showtime Select (next 7 days), clickable seat grid (state via
  `ShowtimeOccupancy::seatStatesFor()` — extracted static so the occupancy page and this
  picker share one derivation; now also carries per-seat price), running total, payment
  Radio, optional guest email + notes. Conflicts surface as danger notifications and reload
  the grid. "Walk-up sale" header action on the bookings list.
- Seeder: `bookings.create_walkup` for admin + manager (ops stays read-only).

## Tests

Service (`WalkUpBookingServiceTest`): cash happy path (Confirmed, section-multiplied totals,
seats occupied, activity with actor), comp zero-total semantics, seat conflict leaves no
orphan booking, past/cancelled showtime refusals, non-POS method refused, empty seats
refused. Page (`CreateWalkUpBookingTest`): permission gate, end-to-end select→toggle→create,
taken seats not toggleable.
