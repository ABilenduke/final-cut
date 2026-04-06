# Data Models & API Routes

TypeScript interfaces for all data types, server API route inventory, and integration details for TMDB and Stripe.

All types are defined in `app/types/` and auto-imported by Nuxt.

> **Currency convention:** All monetary values (prices, totals, discounts, balances) are positive integers in cents (USD). `$12.99` → `1299`. Never use floats for money. See CLAUDE.md for rationale.

---

## 1. TypeScript Interfaces

### Movie

```typescript
// app/types/movie.ts

interface Movie {
  id: number                    // Auto-increment PK (not TMDB ID)
  slug: string                  // URL-safe slug derived from title
  title: string
  tagline: string
  synopsis: string              // TMDB "overview"
  runtime: number               // Minutes
  rating: number                // TMDB vote_average (0-10)
  releaseDate: string           // ISO date
  genres: Genre[]
  cast: CastMember[]
  posterUrl: string             // TMDB poster image URL
  backdropUrl: string           // TMDB backdrop image URL
  trailerKey: string | null     // YouTube video ID from TMDB videos
  status: 'now_showing' | 'coming_soon'
}

interface Genre {
  id: number
  name: string
}

interface CastMember {
  id: number
  name: string
  character: string
  profileUrl: string | null     // TMDB profile image URL
}
```

### Showtime

```typescript
// app/types/showtime.ts

interface Showtime {
  id: string                    // Unique showtime ID
  movieId: number               // References Movie.id
  movieSlug: string             // For URL construction
  movieTitle: string            // Denormalized for display
  screenId: string              // References Auditorium.id
  screenName: string            // Denormalized (e.g., "Screen 1")
  startTime: string             // ISO datetime
  endTime: string               // ISO datetime
  priceStandard: number         // Base price in cents
  pricePremium: number          // Premium seat price in cents
  priceAccessible: number       // Accessible seat price in cents
}
```

### Auditorium

```typescript
// app/types/auditorium.ts

interface Auditorium {
  id: string
  name: string                  // e.g., "Screen 1", "IMAX"
  rows: AuditoriumRow[]
  seatsPerRow: number           // Maximum seats in any row
  totalSeats: number
  sections: AuditoriumSection[]
}

interface AuditoriumRow {
  label: string                 // e.g., "A", "B", "C"
  seats: Seat[]
  section: string               // References AuditoriumSection.name
}

interface AuditoriumSection {
  name: string                  // e.g., "Standard", "Premium", "Accessible"
  priceMultiplier: number       // Applied to showtime base price
}

interface Seat {
  id: string                    // e.g., "A1", "B12"
  row: string                   // Row label
  number: number                // Seat number within row
  status: 'available' | 'taken' | 'held'  // Server-side only. Client selection is tracked separately via selectedSeatIds set (see STATE_MANAGEMENT.md).
  type: 'standard' | 'premium' | 'accessible'
  price: number                 // Final price in cents (calculated from showtime + section)
}
```

### Booking

```typescript
// app/types/booking.ts

interface Booking {
  id: string
  confirmationCode: string      // Human-readable reference (e.g., "CVF-A3X9K2")
  showtimeId: string
  movieTitle: string            // Denormalized
  moviePosterUrl: string        // Denormalized
  screenName: string            // Denormalized
  startTime: string             // ISO datetime
  seats: BookingSeat[]
  foodItems: BookingFoodItem[]
  subtotal: number              // In cents
  discount: number              // In cents (promo codes, gift cards)
  total: number                 // In cents
  paymentMethod: 'card' | 'gift_card' | 'mixed'
  userId: string | null         // Null for guest checkout
  guestEmail: string | null     // Set for guest checkout
  status: 'confirmed' | 'cancelled' | 'refunded'
  createdAt: string             // ISO datetime
}

interface BookingSeat {
  seatId: string                // e.g., "A1"
  section: string               // e.g., "Premium"
  price: number                 // In cents
}

interface BookingFoodItem {
  itemId: string
  name: string
  quantity: number
  unitPrice: number             // In cents
  totalPrice: number            // In cents
}
```

### Calendar Event

```typescript
// app/types/calendar-event.ts

interface CalendarEvent {
  id: string
  type: 'showtime' | 'special_event' | 'loyalty_exclusive' | 'private_screening_blackout'
  title: string
  date: string                  // ISO date
  startTime: string             // ISO datetime
  endTime: string | null        // ISO datetime (null for all-day)
  description: string
  movieSlug: string | null      // Only for showtime type
  imageUrl: string | null       // Event image
  slug: string | null           // URL slug for special events
  ticketUrl: string | null      // Direct link to purchase/RSVP
  loyaltyOnly: boolean          // Visible to all but marked "Members Only"
  accessibilityTags: AccessibilityTag[]  // Empty array if none apply
}

type AccessibilityTag = 'sensory_friendly' | 'open_caption' | 'audio_described'
```

### Menu Item

```typescript
// app/types/menu-item.ts

interface MenuItem {
  id: string
  name: string
  description: string
  price: number                 // In cents
  category: 'popcorn' | 'drinks' | 'snacks' | 'combos' | 'specials'
  imageUrl: string
  allergens: Allergen[]
  dietary: DietaryTag[]
  available: boolean            // Can be temporarily unavailable
}

type Allergen = 'nuts' | 'dairy' | 'gluten' | 'soy' | 'eggs' | 'shellfish'
type DietaryTag = 'vegan' | 'vegetarian' | 'gluten_free'
```

### User

```typescript
// app/types/user.ts

interface User {
  id: string
  email: string
  name: string
  avatarUrl: string | null
  loyaltyPoints: number
  loyaltyTier: 'member' | 'premier'  // member = free (earns 1pt/$), premier = paid annual (10% food discount, birthday ticket, early seat access, exclusive events)
  premierExpiry: string | null  // ISO date — null for member tier
  createdAt: string             // ISO datetime
}

interface UserProfile extends User {
  // Extended fields returned by profile endpoint
  phone: string | null
  dateOfBirth: string | null    // ISO date, for age-restricted content
}
```

### Gift Card

```typescript
// app/types/gift-card.ts

interface GiftCard {
  id: string
  code: string                  // Redemption code
  initialBalance: number        // In cents
  currentBalance: number        // In cents
  recipientEmail: string
  recipientName: string
  senderName: string
  message: string
  purchasedAt: string           // ISO datetime
  status: 'active' | 'depleted' | 'expired'
}
```

### Rental Inquiry

```typescript
// app/types/rental-inquiry.ts

interface RentalInquiry {
  id: string
  eventType: 'birthday' | 'corporate' | 'proposal' | 'custom'
  preferredDate: string         // ISO date
  guestCount: number
  name: string
  email: string
  phone: string | null
  message: string
  status: 'pending' | 'contacted' | 'confirmed' | 'declined'
  createdAt: string             // ISO datetime
}
```

---

## 2. API Route Inventory

All routes are served by the Laravel backend (`backend/routes/api.php`). Data comes from PostgreSQL. TMDB enrichment happens offline via the `movies:enrich` scheduled command — it is never in the request path.

### Movies

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| GET | `/api/movies` | Public | PostgreSQL | `?status=now_showing\|coming_soon&genre=id&page=1` | `{ movies: Movie[], total: number, page: number }` |
| GET | `/api/movies/:slug` | Public | PostgreSQL | — | `Movie` |
| GET | `/api/movies/:slug/showtimes` | Public | PostgreSQL | `?date=YYYY-MM-DD` | `{ showtimes: Showtime[] }` |

Movies are created locally with title, slug, status, and optional `tmdb_id`. TMDB metadata (synopsis, cast, images, trailer, ratings) is backfilled offline by the `movies:enrich` scheduled command. API responses serve only database data.

### Showtimes

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| GET | `/api/showtimes/:id` | Public | Local | — | `Showtime & { auditorium: Auditorium, seats: Seat[] }` |

Returns showtime details with full seat map including current availability. This is the entry point for the seat selection page.

### Booking

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| POST | `/api/bookings` | Public | Local + Stripe | `{ showtimeId, seatIds[], foodItems[], paymentMethodId, email? }` | `{ booking: Booking }` |
| GET | `/api/bookings/:id` | Public* | Local | — | `Booking` |

*`GET /api/bookings/:id` is accessible by booking ID (acts as a secret URL). Authenticated users can also access via their order history.

**POST `/api/bookings` flow:**
1. Validate seat availability (re-check at purchase time)
2. Calculate total (seats + food - discounts)
3. Create Stripe PaymentIntent with calculated amount
4. On payment confirmation: mark seats as taken, create booking record
5. Return booking with confirmation code
6. If seats taken since selection: return `409 Conflict` with which seats are unavailable

### Calendar

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| GET | `/api/calendar/events` | Public | Local | `?month=M&year=Y&type=showtime\|special_event\|loyalty_exclusive&accessibility=sensory_friendly,open_caption` | `{ events: CalendarEvent[] }` |
| GET | `/api/calendar/events/:slug` | Public | Local | — | `CalendarEvent` (full detail) |

### Food Menu

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| GET | `/api/food-menu` | Public | Local | `?category=popcorn\|drinks\|...` | `{ items: MenuItem[] }` |

### Auth

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| POST | `/api/auth/register` | Guest | Local | `{ name, email, password }` | `{ user: User }` + session cookie |
| POST | `/api/auth/login` | Guest | Local | `{ email, password }` | `{ user: User }` + session cookie |
| POST | `/api/auth/logout` | Auth | — | — | Clears session cookie |
| GET | `/api/auth/me` | Auth | Local | — | `{ user: User }` |
| POST | `/api/auth/forgot-password` | Guest | Local | `{ email }` | `{ success: true }` |

Session-based auth via `nuxt-auth-utils`. Sessions stored in encrypted HTTP-only cookies. No JWT.

### Account

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| GET | `/api/account/profile` | Auth | Local | — | `{ data: UserProfile }` |
| PATCH | `/api/account/profile` | Auth | Local | `Partial<UserProfile>` | `{ data: UserProfile }` |
| GET | `/api/account/orders` | Auth | Local | `?page=1&limit=10` | `{ data: Booking[], meta: { total, page, per_page } }` |
| GET | `/api/account/bookings` | Auth | Local | `?upcoming=true` | `{ data: Booking[] }` |
| GET | `/api/account/loyalty` | Auth | Local | — | `{ data: { points, tier, premierExpiry?, history[] } }` |
| GET | `/api/account/payment-methods` | Auth | Stripe | — | `{ data: [{ id, brand, last4, expMonth, expYear }] }` |
| POST | `/api/account/payment-methods` | Auth | Stripe | Stripe SetupIntent flow | `{ data: { clientSecret } }` |
| DELETE | `/api/account/payment-methods/:id` | Auth | Stripe | — | `{ data: { success: true } }` |

### Gift Cards

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| POST | `/api/gift-cards/purchase` | Public | Local + Stripe | `{ amount, recipientEmail, recipientName, senderName, message, paymentMethodId }` | `{ giftCard: GiftCard }` |
| GET | `/api/gift-cards/balance` | Public | Local | `?code=XXXX` | `{ balance: number, status }` |

### Rentals / Contact

| Method | Path | Auth | Data Source | Request | Response |
| ------ | ---- | ---- | ----------- | ------- | -------- |
| POST | `/api/rentals/inquiry` | Public | Local | `RentalInquiry` fields | `{ success: true, inquiryId }` |
| POST | `/api/contact` | Public | Local | `{ name, email, subject, message }` | `{ success: true }` |

---

## 3. TMDB Integration

TMDB is an **offline enrichment source only** — it is never called in the request path. All API responses serve data from PostgreSQL. The Laravel backend's `TmdbService` handles all TMDB communication.

### Image URL Convention

TMDB image URLs stored in the database use the full path format. The frontend renders these directly — no TMDB API calls needed.

- **Image base URL:** `https://image.tmdb.org/t/p/`
- **Image sizes used:**
  - Posters: `w500` (listings), `w780` (detail page)
  - Backdrops: `w1280` (hero sections)
  - Profiles: `w185` (cast list)

### TMDB Enrichment (Offline)

The `movies:enrich` artisan command runs hourly via Laravel's scheduler. For each movie with a `tmdb_id` that needs enrichment, it calls `TmdbService` to backfill metadata (synopsis, cast, images, trailer, ratings). Results are cached in Redis: 24 hours for success, 5 minutes for failures. See `@docs/plans/backend/v1/03-movie-api.md` for implementation details.

The enrichment process maps TMDB data to the local movie schema:
- `tmdbMovie.overview` → `synopsis`
- `tmdbMovie.vote_average` → `rating`
- `credits.cast` (first 12) → `cast` JSON column
- `videos` (first YouTube trailer) → `trailer_key`
- Image paths are stored as full URLs with size prefix (e.g., `https://image.tmdb.org/t/p/w500/...`)

---

## 4. Stripe Integration

### Payment Flow: Ticket Purchase

```
1. Client: User completes seat selection and checkout form
2. Client: Stripe.js creates a PaymentMethod from card details
3. Client: POST /api/bookings with paymentMethodId + order details
4. Server: Validates seat availability (final check)
5. Server: Calculates total
6. Server: Creates Stripe PaymentIntent with amount and paymentMethodId
7. Server: Confirms PaymentIntent
8. Server: On success → creates booking record, marks seats as taken
9. Server: Returns Booking to client
10. Client: Redirects to confirmation page
```

If payment requires 3D Secure:
```
7b. Server returns PaymentIntent with status "requires_action"
8b. Client: Stripe.js handles 3DS modal
9b. Client: On 3DS success, POST /api/bookings/confirm with paymentIntentId
10b. Server: Confirms booking
```

### Payment Flow: Gift Card Purchase

Same pattern as ticket purchase, but:
- Creates a GiftCard record instead of a Booking
- Amount is the gift card value
- Sends email to recipient with gift card code

### Saved Payment Methods

Uses Stripe's SetupIntent flow:
1. Client: POST `/api/account/payment-methods` → server creates SetupIntent
2. Client: Stripe.js collects card via SetupIntent
3. Client: On success, card is attached to Stripe Customer
4. Server: Returns saved PaymentMethod details (brand, last4, expiry)

### Stripe SDK Usage

- **Client (`@stripe/stripe-js`):** Stripe Elements for card input, PaymentMethod creation, 3DS handling
- **Server (`stripe/stripe-php` SDK):** PaymentIntent creation/confirmation, Customer management, SetupIntent creation, webhook handling

### Webhook (Future)

`POST /api/webhooks/stripe` — handles payment confirmation events for reliability. Not required for MVP (server-side confirmation is synchronous), but recommended for production to handle edge cases (network failures between payment and booking creation).

---

## 5. Database & Seeding

All data is served from PostgreSQL. The backend includes comprehensive seeders for development and testing:

- `DatabaseSeeder` — Creates locations, auditoriums, seats, movies, showtimes, users, bookings, gift cards, calendar events, and menu items
- `GiftCardSeeder` — Creates gift cards in various states for testing stateful scenarios

Run `make fresh` from the project root to reset the database with fresh migrations and seeds. See `@docs/plans/backend/v1/08-testing-and-seeding.md` for details.
