# Plan 02: Database Schema

> **Priority:** Must Have
> **Complexity:** L
> **Depends On:** Plan 01 (project config, Sanctum)
> **Unlocks:** Plans 03, 04, 05, 06, 07 (all API plans need models)

## Overview

Create all database models, migrations, factories, and seeders. This plan defines the complete data layer: 11 models with their relationships, database indexes, and realistic development seed data. The schema is designed to support the interfaces defined in `docs/DATA_MODELS.md`.

## Reference Documents

- `docs/DATA_MODELS.md` — TypeScript interfaces (Section 1) — these map to database columns
- `docs/PURCHASE_FLOW.md` — Booking lifecycle, seat states
- `docs/STATE_MANAGEMENT.md` — Data flow patterns

---

## Tasks

### Task 1: Movie Model + Migration

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Models/Movie.php`
  - `backend/database/migrations/xxxx_create_movies_table.php`
  - `backend/database/factories/MovieFactory.php`
- **Details:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `unsignedBigInteger` | TMDB movie ID (not auto-increment) |
  | `slug` | `string` | Unique index, URL-safe |
  | `title` | `string` | |
  | `tagline` | `string`, nullable | |
  | `synopsis` | `text` | TMDB "overview" |
  | `runtime` | `unsignedSmallInteger` | Minutes |
  | `rating` | `decimal(3,1)` | TMDB vote_average 0-10 |
  | `release_date` | `date` | |
  | `genres` | `json` | Array of {id, name} |
  | `poster_url` | `string` | TMDB image URL |
  | `backdrop_url` | `string`, nullable | |
  | `trailer_key` | `string`, nullable | YouTube video ID |
  | `status` | `enum('now_showing', 'coming_soon')` | |
  | `timestamps` | | |

  **Relationships:** `hasMany(Showtime::class)`
  **Indexes:** `slug` (unique), `status`

- **Acceptance Criteria:**
  - [ ] Migration creates table with all columns
  - [ ] Model has correct `$casts` (genres as array, release_date as date)
  - [ ] Factory generates realistic movie data
  - [ ] Slug unique constraint enforced

---

### Task 2: Auditorium + Seat Models + Migrations

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Models/Auditorium.php`
  - `backend/app/Models/Seat.php`
  - `backend/database/migrations/xxxx_create_auditoriums_table.php`
  - `backend/database/migrations/xxxx_create_seats_table.php`
  - Factories for each
- **Details:**
  **Auditorium:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `uuid` | |
  | `name` | `string` | e.g., "Screen 1", "IMAX" |
  | `total_seats` | `unsignedSmallInteger` | |
  | `timestamps` | | |

  **Seat:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `uuid` | Globally unique PK |
  | `auditorium_id` | `uuid` | FK |
  | `label` | `string` | Display label: "A1", "B12" |
  | `row` | `char(1)` | Row letter |
  | `number` | `unsignedSmallInteger` | Seat number |
  | `type` | `enum('standard', 'premium', 'accessible')` | |
  | `timestamps` | | |

  **Relationships:** Auditorium `hasMany(Seat)`, `hasMany(Showtime)`. Seat `belongsTo(Auditorium)`.
  **Indexes:** `seats(auditorium_id, row, number)` unique composite, `seats(auditorium_id, label)` unique composite.

- **Acceptance Criteria:**
  - [ ] Auditorium + Seat tables with correct relationships
  - [ ] Seat PK is UUID; display label (e.g., "A1") stored in `label` column
  - [ ] Unique constraint on (auditorium_id, row, number)
  - [ ] Factory creates auditorium with seats

---

### Task 3: Showtime Model + Migration

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Models/Showtime.php`
  - `backend/database/migrations/xxxx_create_showtimes_table.php`
  - `backend/database/factories/ShowtimeFactory.php`
- **Details:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `uuid` | |
  | `movie_id` | `unsignedBigInteger` | FK to movies |
  | `auditorium_id` | `uuid` | FK to auditoriums |
  | `start_time` | `datetime` | |
  | `end_time` | `datetime` | |
  | `price_standard` | `unsignedInteger` | Cents |
  | `price_premium` | `unsignedInteger` | Cents |
  | `price_accessible` | `unsignedInteger` | Cents |
  | `timestamps` | | |

  **Relationships:** `belongsTo(Movie)`, `belongsTo(Auditorium)`, `hasMany(Booking)`
  **Indexes:** `(movie_id, start_time)`, `start_time`

- **Acceptance Criteria:**
  - [ ] All price columns store cents (integers, not decimals)
  - [ ] Relationships defined correctly
  - [ ] Factory generates showtimes with realistic date/time distribution

---

### Task 4: Booking + BookingSeat + BookingFoodItem Models + Migrations

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Models/Booking.php`
  - `backend/app/Models/BookingSeat.php`
  - `backend/app/Models/BookingFoodItem.php`
  - Migrations for each
  - Factories for each
- **Details:**
  **Booking:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `uuid` | |
  | `confirmation_code` | `string` | Unique, "CVF-" + 6 alphanumeric |
  | `showtime_id` | `uuid` | FK |
  | `user_id` | `uuid`, nullable | FK, null for guest |
  | `guest_email` | `string`, nullable | Set for guest checkout |
  | `status` | `enum('confirmed', 'cancelled', 'refunded')` | |
  | `subtotal` | `unsignedInteger` | Cents |
  | `discount` | `unsignedInteger`, default 0 | Cents |
  | `total` | `unsignedInteger` | Cents |
  | `payment_method` | `enum('card', 'gift_card', 'mixed')` | |
  | `stripe_payment_intent_id` | `string`, nullable | |
  | `timestamps` | | |

  **BookingSeat (pivot):**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `booking_id` | `uuid` | FK |
  | `showtime_id` | `uuid` | FK, denormalized from booking |
  | `seat_id` | `uuid` | FK to seats.id |
  | `section` | `string` | e.g., "Premium" |
  | `price` | `unsignedInteger` | Cents |

  **Indexes:** `booking_seats(showtime_id, seat_id)` unique — enforces one reservation per seat per showtime at the database level.

  > **Note:** `showtime_id` is denormalized onto `booking_seats` (copied from the parent booking) specifically to support this unique constraint. This prevents double-booking even under concurrent inserts.

  **BookingFoodItem:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `uuid` | |
  | `booking_id` | `uuid` | FK |
  | `item_id` | `string` | Reference to menu item |
  | `name` | `string` | Denormalized |
  | `quantity` | `unsignedSmallInteger` | |
  | `unit_price` | `unsignedInteger` | Cents |
  | `total_price` | `unsignedInteger` | Cents |

  **Relationships:** Booking `hasMany(BookingSeat)`, `hasMany(BookingFoodItem)`, `belongsTo(Showtime)`, `belongsTo(User)`.

- **Acceptance Criteria:**
  - [ ] Confirmation code generated with "CVF-" prefix
  - [ ] Guest bookings have null user_id but populated guest_email
  - [ ] All monetary values in cents
  - [ ] Booking relationships work correctly

---

### Task 5: GiftCard Model + Migration

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/app/Models/GiftCard.php`
  - `backend/database/migrations/xxxx_create_gift_cards_table.php`
  - `backend/database/factories/GiftCardFactory.php`
- **Details:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `uuid` | |
  | `code` | `string` | Unique redemption code |
  | `initial_balance` | `unsignedInteger` | Cents |
  | `current_balance` | `unsignedInteger` | Cents |
  | `recipient_email` | `string` | |
  | `recipient_name` | `string` | |
  | `sender_name` | `string` | |
  | `message` | `text` | |
  | `status` | `enum('active', 'depleted', 'expired')` | |
  | `purchased_at` | `datetime` | |
  | `timestamps` | | |

  **Indexes:** `code` (unique)

- **Acceptance Criteria:**
  - [ ] Unique code generation
  - [ ] Balance tracking (initial and current)
  - [ ] Status transitions work

---

### Task 6: CalendarEvent Model + Migration

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/app/Models/CalendarEvent.php`
  - `backend/database/migrations/xxxx_create_calendar_events_table.php`
  - `backend/database/factories/CalendarEventFactory.php`
- **Details:**
  | Column | Type | Notes |
  | --- | --- | --- |
  | `id` | `uuid` | |
  | `type` | `enum('showtime', 'special_event', 'loyalty_exclusive', 'private_screening_blackout')` | |
  | `title` | `string` | |
  | `date` | `date` | |
  | `start_time` | `datetime` | |
  | `end_time` | `datetime`, nullable | Null for all-day |
  | `description` | `text` | |
  | `movie_slug` | `string`, nullable | Only for showtime type |
  | `image_url` | `string`, nullable | |
  | `slug` | `string`, nullable | Unique, for special events |
  | `ticket_url` | `string`, nullable | |
  | `loyalty_only` | `boolean`, default false | |
  | `accessibility_tags` | `json` | Array of AccessibilityTag |
  | `timestamps` | | |

  **Indexes:** `(date, type)`, `slug` (unique, nullable)

- **Acceptance Criteria:**
  - [ ] All event types supported
  - [ ] Accessibility tags stored as JSON array
  - [ ] Filterable by type and accessibility

---

### Task 7: MenuItem + RentalInquiry Models + Migrations

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `backend/app/Models/MenuItem.php`
  - `backend/app/Models/RentalInquiry.php`
  - Migrations and factories for each
- **Details:**
  **MenuItem:** id (uuid), name, description, price (cents), category (enum), image_url, allergens (json), dietary (json), available (boolean), timestamps.

  **RentalInquiry:** id (uuid), event_type (enum), preferred_date, guest_count, name, email, phone (nullable), message, status (enum), timestamps.

- **Acceptance Criteria:**
  - [ ] MenuItem categories match DATA_MODELS.md (popcorn, drinks, snacks, combos, specials)
  - [ ] RentalInquiry event types match (birthday, corporate, proposal, custom)
  - [ ] JSON columns for allergens and dietary tags

---

### Task 8: Update User Model

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/app/Models/User.php` — Update existing
  - `backend/database/migrations/xxxx_add_profile_fields_to_users_table.php`
  - `backend/database/factories/UserFactory.php` — Update existing
- **Details:**
  Add columns per DATA_MODELS.md User/UserProfile interfaces:
  | Column | Type | Notes |
  | --- | --- | --- |
  | `phone` | `string`, nullable | |
  | `date_of_birth` | `date`, nullable | |
  | `avatar_url` | `string`, nullable | |
  | `loyalty_points` | `unsignedInteger`, default 0 | |
  | `loyalty_tier` | `enum('member', 'premier')`, default 'member' | |
  | `premier_expiry` | `date`, nullable | Null for member tier |

  Also add `stripe_customer_id` (string, nullable) for Stripe Customer association.

- **Acceptance Criteria:**
  - [ ] Migration adds all new columns without breaking existing data
  - [ ] User model has correct `$casts`
  - [ ] Factory generates complete user profiles
  - [ ] Loyalty defaults work (0 points, member tier)

---

### Task 9: DatabaseSeeder

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/database/seeders/DatabaseSeeder.php`
  - `backend/database/seeders/MovieSeeder.php`
  - `backend/database/seeders/AuditoriumSeeder.php`
  - `backend/database/seeders/ShowtimeSeeder.php`
  - `backend/database/seeders/CalendarEventSeeder.php`
  - `backend/database/seeders/MenuItemSeeder.php`
- **Details:**
  Seed realistic development data:
  - **Movies:** 20 movies (12 now_showing, 8 coming_soon) with realistic titles, genres, ratings
  - **Auditoriums:** 3 layouts — Small (8×10, "Screen 1"), Medium (12×14, "Screen 2"), Large/IMAX (15×20, "IMAX")
  - **Seats:** Generated per auditorium layout with standard, premium (front 3 rows), and accessible (back row, aisle seats) sections
  - **Showtimes:** 50+ across next 14 days, 2-4 per movie per day, distributed across auditoriums
  - **Calendar Events:** 10 events (mix of special_event, loyalty_exclusive, with accessibility tags)
  - **Menu Items:** 20+ items across all categories with allergens and dietary info
  - **Test User:** `test@finalcut.test` / `password`, premier tier, 500 loyalty points
  - **Sample Bookings:** 5 bookings for test user (mix of upcoming and past)

- **Acceptance Criteria:**
  - [ ] `php artisan migrate:fresh --seed` runs without errors
  - [ ] All 20 movies created with correct statuses
  - [ ] 3 auditoriums with proper seat layouts
  - [ ] Showtimes span next 14 days
  - [ ] Test user has bookings and loyalty data
  - [ ] Seed is idempotent (can re-run safely)

---

## Testing Requirements

- **Migration Test:** `php artisan migrate:fresh --seed` runs cleanly
- **Factory Tests:** Each factory creates valid model instances
- **Relationship Tests:** Verify model relationships (Movie→Showtimes, Booking→Seats, etc.)
- **Constraint Tests:** Unique constraints enforced (slug, confirmation code, seat position)

## Dependencies Map

```
Task 1 (Movie) ← independent
Task 2 (Auditorium + Seat) ← independent
Task 3 (Showtime) ← needs Task 1, 2
Task 4 (Booking + BookingSeat + BookingFoodItem) ← needs Task 3, 8
Task 5 (GiftCard) ← independent
Task 6 (CalendarEvent) ← independent
Task 7 (MenuItem + RentalInquiry) ← independent
Task 8 (Update User) ← independent
Task 9 (Seeder) ← needs all above
```

## Risks & Open Questions

1. **UUID primary keys** — Most models use UUIDs. Ensure `HasUuids` trait or `$keyType = 'string'` is set. Movie uses TMDB integer ID as PK, not UUID.
2. **Seat identity** — Seats use UUID primary keys for globally unique identity. The display label (e.g., "A1") is stored in a separate `label` column with a unique constraint scoped to `(auditorium_id, label)`. BookingSeat references seats by UUID, so joins are unambiguous across auditoriums.
3. **Booking seat pivot** — BookingSeat links bookings to seats. This is a pivot table but also stores price data and a denormalized `showtime_id`, so it's modeled as a full model (not just `belongsToMany` with pivot). The `showtime_id` duplication enables the `(showtime_id, seat_id)` unique constraint that prevents double-booking at the database level.
4. **Price precision** — All prices in cents (integers). No floating-point math for money. Frontend formats with `formatCurrency` utility.
