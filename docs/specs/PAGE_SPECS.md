# Page Specifications

Comprehensive specification for every page on the movie theatre website, grouped by implementation tier.

---

## Layouts

| Layout      | Description                                                        |
| ----------- | ------------------------------------------------------------------ |
| `default`   | Standard site layout with header (Neural Ticker, location switcher), nav, and footer  |
| `account`   | Sidebar navigation for account management pages                    |
| `purchase`  | Streamlined layout for the ticket purchase flow                    |
| `blank`     | No chrome -- logo only, used for auth screens                      |

## Layout Compositions

| Composition          | Description                                                  |
| -------------------- | ------------------------------------------------------------ |
| Establishing Shot    | 65/35 two-column split, primary content left                 |
| Rack Focus           | 35/65 two-column split, primary content right                |
| Wide Frame           | Full-width section spanning the entire content area          |
| Close-Up             | Centered narrow column, max-width 40rem                      |
| Ensemble             | Responsive grid of equally weighted cards                    |
| Auditorium           | Specialized seat-map grid with pinned row labels             |

---

## Essential

### `/` -- Home

| Property     | Value                                                                           |
| ------------ | ------------------------------------------------------------------------------- |
| Layout       | `default`                                                                       |
| Compositions | Wide Frame (featured carousel + food teaser), Ensemble (now showing, coming soon, locations), Close-Up (week strip)  |
| Auth         | Public                                                                          |

**Sections (top to bottom)**

1. **Featured Hero Carousel** -- Admin-curated rotating slides at the top of the page. Each slide is a full-bleed image with headline, optional sub-headline, and CTA. Sourced from `GET /api/featured-slides`. See *Featured Hero Carousel Specification* below for the full component contract. Falls back to a single hardcoded brand slide when the API returns zero.
2. **Now Showing** -- Cross-location Ensemble grid of every currently-playing movie card (poster, title, rating badge, next available showtime as a clickable time chip linking to `/purchase/:showtimeId`). No location filter — the full slate of films across all venues. The path from title → time → purchase should be a single click.
3. **Coming Soon** -- Cross-location Ensemble grid of upcoming releases with release-date captions and a "Notify Me" action instead of a showtime. Also drawn from the full slate.
4. **What's On This Week** -- Compact event preview strip for the next 7 days, cross-location, sourced from `useCalendarEvents`. Each entry shows date, title, type badge, and a link to `/events/:slug` or `/whats-on?date=...`.
5. **Food & Drink Teaser** -- Editorial Wide Frame section: hero image, short pitch, "Explore the Menu" CTA → `/food-drink`. Visual, not interactive. No menu data fetched at this position.
6. **Locations Strip** -- Ensemble of compact location cards (each: name, neighborhood, "See Showtimes" CTA → `/movies?location={slug}` and "Get Directions" link). Links into `/locations`. After hydration, `useGeolocation` reorders cards by distance and adds "X mi away" captions; SSR ships alphabetical order.
7. **Neural Ticker** -- Ambient showtimes and events ticker rendered in the layout header.

**Components**

`HomeFeaturedCarousel`, `MovieCard`, `EventListCard`, `LocationTeaserCard`, `CvButton`

**Data Requirements**

- `GET /api/featured-slides`
- `GET /api/movies?status=now_showing&per_page=12`
- `GET /api/movies?status=coming_soon&per_page=8`
- `GET /api/calendar/events?range=week`
- `GET /api/locations`

**SEO**

- Title: `Final Cut -- Now Showing at All Locations`
- Description: cross-location, brand-led, mentions both venues
- Structured data: `ItemList` (Movie) for the now-showing slate; `Organization` for the brand; emit `LocalBusiness` references for each venue (full schemas live on `/locations/:slug`)

**Featured Hero Carousel Specification**

Component: `HomeFeaturedCarousel`

| Behavior | Spec |
|---|---|
| Auto-advance | 7 second interval; pauses on hover, focus, or when the tab is hidden |
| Controls | Prev/Next buttons (always visible on desktop, swipe-only on mobile), dot indicators below the slide |
| Touch | Horizontal swipe with `scroll-snap` on the slide container |
| Transition | Crossfade `duration-emphasis` (400ms) `ease-standard`; reduced motion → instant cut |
| Empty state | When the API returns zero slides, render a single hardcoded brand slide (poster collage + tagline + CTA → `/movies`) |
| Reduced motion | Auto-advance disabled; all slides stacked statically with the first visible; indicators repurposed as nav buttons |

Accessibility:
- `role="region"`, `aria-roledescription="carousel"`, `aria-label="Featured"`.
- Each panel: `role="group"`, `aria-roledescription="slide"`, `aria-label="Slide N of M: {headline}"`.
- `aria-live="polite"` on the slide container while paused; `"off"` while auto-advancing (per WAI-ARIA carousel pattern).
- Prev/Next buttons: `aria-label="Previous slide" / "Next slide"`.
- Dots: `<button aria-label="Go to slide N" aria-current="true|false">`.

---

### `/movies` -- Movie Listings

| Property     | Value                        |
| ------------ | ---------------------------- |
| Layout       | `default`                    |
| Compositions | Ensemble grid                |
| Auth         | Public                       |

**Sections (top to bottom)**

1. **Tab bar / toggle** -- Now Showing and Coming Soon tabs.
2. **Location filter chip row** -- "All Locations" (default) | "Downtown" | "Uptown". Cross-location browsing is the default; the chips are an opt-in narrowing. Selecting a chip writes `?location=slug` to the URL — the URL is the source of truth, every filtered URL is independently ISR-cacheable. After hydration, if `useGeolocation` resolves with `granted`, surface a non-binding "Filter to nearest: {Location Name}" suggestion chip below the row (a single click applies the filter).
3. **Genre/rating filters** -- Secondary filter row; query params `genre`, `rating`.
4. **Movie grid** -- Ensemble grid of movie cards. With `?location=slug`: only movies that have at least one upcoming showtime at that location.

**Components**

`MovieCard`, `LocationFilterChips`, `CvBadge` (genre tags)

**Data Requirements**

- `GET /api/movies?status=now_showing` or `?status=coming_soon`
- Query params: `location`, `genre`, `rating`
- `GET /api/locations` (for the chip row)

**SEO**

- Default URL (no `?location=`): `Now Showing at All Locations -- Final Cut`
- Filtered URL (`?location=downtown`): `Now Showing at Downtown -- Final Cut`. Every filtered URL emits `<link rel="canonical" href="/movies?location=downtown">` (the filtered URL is the canonical for that filtered view) and `<link rel="alternate">` back to the unfiltered listing.
- Structured data: `ItemList` of `Movie`

---

### `/movies/:slug` -- Movie Detail

| Property     | Value                                                               |
| ------------ | ------------------------------------------------------------------- |
| Layout       | `default`                                                           |
| Compositions | Wide Frame (hero backdrop), Establishing Shot 65/35 (main content)  |
| Auth         | Public                                                              |

**Sections (top to bottom)**

1. **Wide Frame hero** -- Backdrop image with vignette bloom gradient.
2. **Establishing Shot -- Left (65%)** -- Title, tagline, synopsis, genre badges, runtime, rating. Trailer embed (YouTube iframe). Cast list with actor photos and names sourced from TMDB credits.
3. **Establishing Shot -- Right (35%)** -- Cross-location showtime selector. Date tabs at the top (today highlighted, horizontally scrollable). Under each selected date, showtimes are **grouped by location** (one heading per venue, e.g. "Downtown" / "Uptown") with the time-slot buttons listed below each heading. Each time button routes directly to `/purchase/{showtimeId}` — the showtime ID encodes the location, so no intermediate location confirmation step is required at click time. The location confirmation lives on the seat-selection page.

**Default-expanded behavior:**
- When `useGeolocation.status === 'granted'`: the closest location's group is expanded by default; other groups render collapsed-but-visible (header visible with a one-click expand). Each group caption shows distance ("Downtown · 2.3 mi away").
- Without geolocation: every group renders expanded in alphabetical order; no distance captions.
- Reduced motion: expand/collapse is instant (no animation).

**Empty states:**
- Movie has zero upcoming showtimes anywhere → "Showtimes coming soon" placeholder + "Notify Me" CTA.
- Movie has showtimes at one venue but not another → only the venue with showtimes renders; no empty placeholder for the other.

**Components**

`MovieHero` (backdrop variant), `MovieDetail`, `MovieTrailerEmbed`, `MovieCastList`, `MovieRatingBadge`, `ShowtimeSelector`, `CvBadge`

**Data Requirements**

- `GET /api/movies/:slug`
- `GET /api/movies/:slug/showtimes` -- cross-location, returns `[{ ...showtime, location: { slug, name, latitude, longitude } }]` so the client can group and (post-hydration) compute distances. Replaces the old per-location `GET /api/locations/{slug}/movies/{slug}/showtimes` for the public path; the per-location endpoint stays for admin/internal use.
- `GET /api/locations` (memoized; used to compute distance captions when geolocation is granted)

**SEO**

- Title: `[Movie Title] -- Showtimes at All Locations -- Final Cut`
- Description mentions both venues
- Structured data: `Movie` schema with one `screeningEvent` per showtime (each carries the venue's `LocalBusiness` reference), `VideoObject` for trailer
- Single canonical URL per movie regardless of location filter

---

### `/contact` -- Contact / General

| Property     | Value                        |
| ------------ | ---------------------------- |
| Layout       | `default`                    |
| Compositions | Establishing Shot 65/35      |
| Auth         | Public                       |

**Sections (top to bottom)**

1. **Left (65%)** -- Brand-led "How can we help" copy. Brief venue overview with a clear pointer to `/locations` for venue-specific addresses, hours, directions, and accessibility info.
2. **Right (35%)** -- General contact form (name, email, subject, message). Phone number and email for non-booking inquiries.

**Components**

`ContactForm`, `CvInput`, `CvTextarea`, `CvButton`

**Data Requirements**

- Static content (hardcoded or from `app.config.ts`)
- `POST /api/contact` (form submission)

**SEO**

- Title: `Contact -- Final Cut`
- Structured data: `Organization` (per-venue `LocalBusiness` lives on `/locations/:slug`)

---

### `/locations` -- All Locations

| Property     | Value                        |
| ------------ | ---------------------------- |
| Layout       | `default`                    |
| Compositions | Wide Frame (hero), Ensemble grid (location cards) |
| Auth         | Public                       |

**Sections (top to bottom)**

1. **Wide Frame hero** -- Brand-led "Two cinemas, one obsession" hero copy + image.
2. **Locations grid** -- Ensemble grid of `LocationCard` components, one per venue. Each card shows: thumbnail photo, name, neighborhood/city, full street address, phone, hours summary, "Get Directions" link (`https://maps.google.com/?q=<lat>,<lng>` constructed client-side), and a "See Showtimes" CTA → `/movies?location={slug}`. Card itself is a clickable link to `/locations/:slug`.
3. **Editorial closer** -- Optional "Coming to a third location" / "What we look for in a venue" editorial paragraph (CMS-driven later; static for v1).

**Geolocation enhancement (post-hydration):** When `useGeolocation.status === 'granted'`, cards re-order by distance and each gains a "X mi away" caption. SSR ships alphabetical order with no captions — geolocation is a hydration-time enhancement only.

**Components**

`LocationCard`, `CvButton`, `CvCard`

**Data Requirements**

- `GET /api/locations` -- returns full venue payloads (name, slug, phone, email, address fields, country, timezone, latitude, longitude, hours)

**SEO**

- Title: `Our Cinemas -- Final Cut`
- Description: brand + city names
- Structured data: `ItemList` of `LocalBusiness` references; each card's `LocalBusiness` schema is fully emitted on `/locations/:slug`

---

### `/locations/:slug` -- Location Detail

| Property     | Value                              |
| ------------ | ---------------------------------- |
| Layout       | `default`                          |
| Compositions | Wide Frame (hero), Establishing Shot 65/35 |
| Auth         | Public                             |

**Sections (top to bottom)**

1. **Wide Frame hero** -- Venue photo + name overlay.
2. **Establishing Shot -- Left (65%)**
   - Full street address, city, state, postal code, country
   - Phone, email
   - Hours of operation table
   - Embedded map (or static map image with the directions link as primary CTA — design call in the frontend plan)
   - Driving / transit / walking directions
   - Parking info
   - Accessibility info (ramp locations, accessible parking, assisted listening, sensory-friendly schedule pointer to `/whats-on?accessibility=sensory_friendly`)
3. **Establishing Shot -- Right (35%)**
   - "Now Showing Here" -- compact strip of currently-playing movies at this venue (cross-references `/api/movies?location=:slug`). Each tile links to `/movies/:slug` (the movie's canonical detail page; the showtime selector there will default-expand this venue's group via geolocation if the user has granted it).
   - "Upcoming Events Here" -- next 5 events at this venue.
   - "Get Directions" CTA (Google Maps URL).
   - "Call" CTA (`tel:` link).

**Components**

`LocationHero`, `LocationDetailPanel`, `MovieCard`, `EventListCard`, `CvButton`

**Data Requirements**

- `GET /api/locations/:slug` -- full venue payload
- `GET /api/movies?location=:slug&status=now_showing` -- now-showing-here strip
- `GET /api/calendar/events?location=:slug&range=upcoming&per_page=5` -- upcoming-events-here strip (if backend supports `?location=` on events; otherwise client-side filter on the cross-location response)

**SEO**

- Title: `{Location Name} -- Final Cut`
- Description: address + city + "Now showing: {top movie titles}"
- Open Graph image: venue photo
- Structured data: full `LocalBusiness` JSON-LD (`name`, `address` as `PostalAddress`, `telephone`, `email`, `geo` as `GeoCoordinates`, `openingHoursSpecification`, `image`, `priceRange`, `url`)
- Canonical: `/locations/:slug`

---

### `/faq` -- FAQ / Help

| Property     | Value                                          |
| ------------ | ---------------------------------------------- |
| Layout       | `default`                                      |
| Compositions | Close-Up (centered narrow column, 40rem max)   |
| Auth         | Public                                         |

**Sections (top to bottom)**

1. **Page title.**
2. **Category sections**, each containing an accordion group:
   - Tickets & Booking (refunds, exchanges, group bookings)
   - Age Restrictions & Ratings
   - Accessibility (assisted listening, wheelchair, captions)
   - Food & Allergies
   - Policies (bags, outside food, late arrival)

**Components**

`FaqAccordionGroup`, `CvAccordion`

**Data Requirements**

- Static from `app/data/faq.ts` or `@nuxt/content` markdown files

**SEO**

- Title: `FAQ -- [Theater Name]`
- Structured data: `FAQPage`

---

## Auth & Account

### `/auth/login` -- Login

| Property     | Value                                                         |
| ------------ | ------------------------------------------------------------- |
| Layout       | `blank`                                                       |
| Compositions | Close-Up                                                      |
| Auth         | Guest-only (redirect to `/account` if already authenticated)  |

**Sections (top to bottom)**

1. Theater logo.
2. Login form (email and password).
3. "Forgot password?" link.
4. "Create account" link.

**Components**

`CvInput`, `CvButton`

**Data Requirements**

- `POST /api/auth/login`

**SEO**

- Title: `Sign In -- [Theater Name]`
- `noindex`

---

### `/auth/register` -- Register

| Property     | Value       |
| ------------ | ----------- |
| Layout       | `blank`     |
| Compositions | Close-Up    |
| Auth         | Guest-only  |

**Sections (top to bottom)**

1. Theater logo.
2. Registration form (name, email, password, confirm password).
3. Terms acceptance checkbox.
4. "Already have an account?" link.

**Components**

`CvInput`, `CvButton`

**Data Requirements**

- `POST /api/auth/register`

**SEO**

- Title: `Create Account -- [Theater Name]`
- `noindex`

---

### `/auth/forgot-password` -- Forgot Password

| Property     | Value       |
| ------------ | ----------- |
| Layout       | `blank`     |
| Compositions | Close-Up    |
| Auth         | Guest-only  |

**Sections (top to bottom)**

1. Theater logo.
2. Email input.
3. "Send Reset Link" button.
4. Back to login link.

**Components**

`CvInput`, `CvButton`

**Data Requirements**

- `POST /api/auth/forgot-password`

**SEO**

- `noindex`

---

### `/auth/reset-password` -- Reset Password

| Property     | Value       |
| ------------ | ----------- |
| Layout       | `blank`     |
| Compositions | Close-Up    |
| Auth         | Guest-only  |

**Sections (top to bottom)**

1. Theater logo.
2. New password input.
3. Confirm password input.
4. "Reset Password" button.
5. Back to login link.

**Components**

`CvInput`, `CvButton`

**Data Requirements**

- Reads `token` and `email` from URL query params
- `POST /api/auth/reset-password` with `{ token, email, password, password_confirmation }`

**SEO**

- `noindex`

---

### `/account` -- Account Dashboard

| Property     | Value                                                   |
| ------------ | ------------------------------------------------------- |
| Layout       | `account` (sidebar)                                     |
| Compositions | Establishing Shot 65/35 within main content area        |
| Auth         | Authenticated                                           |

**Sections (top to bottom)**

1. **Left (65%)** -- Upcoming bookings (next 3). Recent orders (last 5).
2. **Right (35%)** -- Profile summary (name, email, avatar). Loyalty points card (points balance, tier). Quick action links (edit profile, manage payment methods).

**Components**

`UpcomingBookings`, `OrderHistoryList`, `LoyaltyPointsCard`, `CvCard`, `CvButton`

**Data Requirements**

- `GET /api/account/profile`
- `GET /api/account/orders?limit=5`
- `GET /api/account/bookings?upcoming=true`
- `GET /api/account/loyalty`

**SEO**

- `noindex`

---

### `/account/profile` -- Edit Profile

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `account`       |
| Compositions | Close-Up        |
| Auth         | Authenticated   |

**Sections (top to bottom)**

1. Avatar upload.
2. Name and email fields.
3. Phone number field (optional).
4. Date of birth field (optional).
5. Password change (current, new, confirm).
6. Save button.

**Components**

`ProfileForm`, `CvInput`, `CvButton`

**Data Requirements**

- `GET /api/account/profile`
- `PATCH /api/account/profile`

**SEO**

- `noindex`

---

### `/account/orders` -- Order History

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `account`       |
| Compositions | Close-Up (list) |
| Auth         | Authenticated   |

**Sections (top to bottom)**

1. Paginated order list. Each order expands to show: movie, date, time, seats, food items, total, and booking reference.

**Components**

`OrderHistoryList`, `CvAccordion`, `CvBadge`

**Data Requirements**

- `GET /api/account/orders?page=N`

**SEO**

- `noindex`

---

### `/account/loyalty` -- Loyalty Program

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `account`       |
| Compositions | Close-Up        |
| Auth         | Authenticated   |

**Sections (top to bottom)**

1. **Points balance** -- current points and value toward next $5 reward.
2. **Tier status** -- Member or Premier badge. Member tier shows "Upgrade to Premier" CTA with perks summary (10% food discount, birthday ticket, early seat access, exclusive events). Premier tier shows renewal date and active perks.
3. **Points history** -- earned and redeemed transactions.
4. **Available rewards** -- redeemable rewards at current point balance.

**Components**

`LoyaltyPointsCard`, `CvCard`, `CvBadge`, `CvButton`

**Data Requirements**

- `GET /api/account/loyalty`

**SEO**

- `noindex`

---

### `/account/bookings` -- Upcoming Bookings

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `account`       |
| Compositions | Close-Up (list) |
| Auth         | Authenticated   |

**Sections (top to bottom)**

1. List of upcoming bookings with movie poster, title, date, time, and seats. Each links to the booking confirmation page.

**Components**

`UpcomingBookings`, `CvCard`

**Data Requirements**

- `GET /api/account/bookings?upcoming=true`

**SEO**

- `noindex`

---

### `/account/payment-methods` -- Saved Payment Methods

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `account`       |
| Compositions | Close-Up        |
| Auth         | Authenticated   |

**Sections (top to bottom)**

1. List of saved cards (last 4 digits, expiry, brand icon).
2. Add new card button.
3. Delete card action.

**Components**

`SavedPaymentMethods`, `CvCard`, `CvButton`

**Data Requirements**

- `GET /api/account/payment-methods` (via Stripe Customer)
- `POST /api/account/payment-methods`
- `DELETE /api/account/payment-methods/:id`

**SEO**

- `noindex`

---

## Purchase Flow

### `/purchase/:showtimeId` -- Pick Your Seats

| Property     | Value                                              |
| ------------ | -------------------------------------------------- |
| Layout       | `purchase`                                         |
| Compositions | Auditorium (seat grid), with CartSummary sidebar   |
| Auth         | Public (guest checkout supported), prompts login for loyalty points |

**Sections (top to bottom)**

1. **Booking Location Banner** -- New top-of-page band (`BookingLocationBanner` component): "You're booking at **{Location Name}** — {street address}, {city}. {phone}." Includes a "[Change location]" link that returns to the movie detail page so the user can pick a different venue's showtime. This is the single explicit moment where the user sees and consciously commits to the venue. Reads `showtime.location` from the seatmap fetch — no separate request.
2. **Movie title and showtime info bar.**
3. **Screen indicator** -- visual bar representing the screen position.
4. **Seat grid** -- Interactive grid with row labels pinned left, scrollable on mobile.
5. **Seat legend** -- Available, selected, taken, accessible, premium.
6. **Cart summary** -- Sidebar on desktop, bottom sheet on mobile. Shows selected seats and running total.
7. **"Continue to Checkout" CTA.**

**Components**

`BookingLocationBanner`, `AuditoriumGrid`, `AuditoriumSeat`, `AuditoriumScreenBar`, `AuditoriumLegend`, `CartSummary`, `CvButton`

**Data Requirements**

- `GET /api/locations/{location}/showtimes/:id` (includes seat map, availability, and the venue payload that feeds `BookingLocationBanner`). The `{location}` segment is part of the showtime ID's URL contract — it's not a runtime selection.

**SEO**

- `noindex`

---

### `/purchase/checkout` -- Add Food & Pay

| Property     | Value                                                    |
| ------------ | -------------------------------------------------------- |
| Layout       | `purchase`                                               |
| Compositions | Establishing Shot 65/35                                  |
| Auth         | Public (guest checkout with email) or Authenticated      |

**Sections (top to bottom)**

1. **Left (65%)** -- Order summary (movie, showtime, seats, prices). Food pre-order panel: renders the **shared cross-location menu** (`GET /api/food-menu`) but receives the booking's location slug as a prop. Items whose `available_at` array excludes that slug render `disabled` with a `CvBadge variant="warning"` overlay: "Not available at {Location Name}". Quantity controls are hidden for unavailable items; the cart never accepts them. Promo code input. Gift card redemption input.
2. **Right (35%)** -- Checkout form with Stripe Elements (card input, billing). Guest email field if not logged in. "Complete Purchase" CTA.

**Components**

`CartSummary`, `FoodPreOrderPanel`, `PromoCode`, `CheckoutForm`, `CvInput`, `CvButton`, `CvBadge`

**Data Requirements**

- `GET /api/food-menu` (shared cross-location menu; per-item `available_at: string[]` drives the dim/disable overlay)
- `POST /api/locations/{location}/bookings` (creates PaymentIntent, validates seats, processes order; server-side rejects food items not stocked at the booking's location as defense-in-depth)

**SEO**

- `noindex`

---

### `/purchase/confirmation/:bookingId` -- You're In

| Property     | Value                                                              |
| ------------ | ------------------------------------------------------------------ |
| Layout       | `purchase` (or `blank` for print)                                  |
| Compositions | Close-Up                                                           |
| Auth         | Public (accessible via booking ID in URL) or Authenticated         |

**Sections (top to bottom)**

1. Success message.
2. Booking reference number.
3. Movie title, date, time, screen.
4. Seat numbers and section.
5. Food pre-orders (if any).
6. Total paid.
7. QR code (generated client-side from booking ID).
8. "Add to Calendar" button (.ics download).
9. "Print Tickets" button.
10. Links: "View in Order History" (if logged in), "Back to Home".

**Components**

`BookingConfirmation`, `CvButton`

**Data Requirements**

- `GET /api/bookings/:id`

**SEO**

- `noindex`

---

## Important

### `/food-drink` -- Food & Drink Menu

| Property     | Value                              |
| ------------ | ---------------------------------- |
| Layout       | `default`                          |
| Compositions | Wide Frame (hero), Ensemble grid with tab bar |
| Auth         | Public                             |

**Sections (top to bottom)**

1. **Editorial hero** -- Wide Frame brand-led hero (image + tagline). Sets the menu as a destination, not a list of utilities.
2. **Category tabs** -- Popcorn, Drinks, Snacks, Combos, Specials. Operates on the full cross-location item set.
3. **Menu grid** -- Ensemble grid of every menu item across every venue: image, name, description, price, dietary badges. Items whose `available_at` is a strict subset of all locations carry an inline caption: "Available at Downtown only" (one location) or "Available at Downtown · Uptown" (subset). Items available everywhere carry no caption — that's the default.
4. **Allergen / dietary filters** -- Secondary filter row.
5. **Footer note** -- "Selection may vary by location. The full picture lives in your booking confirmation."

**Components**

`MenuCategoryTabs`, `MenuItem`, `CvBadge`, `CvCard`

**Data Requirements**

- `GET /api/food-menu` -- shared cross-location endpoint. Each item carries `available_at: string[]` (location slugs). Replaces the per-location endpoint for the public path; the per-location endpoint stays for admin/internal use.

**SEO**

- Title: `Food & Drink -- Final Cut`
- Description: cinematic-positioning copy, mentions both venues
- Structured data: `Menu` with one `MenuItem` entry per item; per-item availability captured in description text (no schema.org primitive for "available at one location of a multi-location business")

---

### `/private-screenings` -- Private Screenings / Rentals

| Property     | Value                   |
| ------------ | ----------------------- |
| Layout       | `default`               |
| Compositions | Rack Focus 35/65        |
| Auth         | Public                  |

**Sections (top to bottom)**

1. **Left (35%)** -- Rental inquiry form with date picker, event type dropdown, guest count, name, email, message, and submit button.
2. **Right (65%)** -- Package descriptions (birthday, corporate, proposal, custom). Pricing tiers. What's included (screen size, capacity, food options, AV equipment). Photo gallery of past events.

**Components**

`RentalInquiryForm`, `PackageCard`, `CvInput`, `CvSelect`, `CvTextarea`, `CvButton`, `CvCard`

**Data Requirements**

- Static content
- `POST /api/rentals/inquiry` (form submission)

**SEO**

- Title: `Private Screenings & Events -- [Theater Name]`

---

### `/gift-cards` -- Gift Cards

| Property     | Value                        |
| ------------ | ---------------------------- |
| Layout       | `default`                    |
| Compositions | Establishing Shot 65/35      |
| Auth         | Public                       |

**Sections (top to bottom)**

1. **Left (65%)** -- Gift card visual preview. Amount selector (preset amounts and custom). Recipient details (name, email, personal message). "Purchase" CTA (Stripe payment).
2. **Right (35%)** -- Balance checker with card number/code input, "Check Balance" button, and balance display.

**Components**

`GiftCardPurchase`, `BalanceChecker`, `CvInput`, `CvButton`

**Data Requirements**

- `POST /api/gift-cards/purchase` (Stripe)
- `GET /api/gift-cards/balance?code=X`

**SEO**

- Title: `Gift Cards -- [Theater Name]`

---

## Calendar & Events

### `/whats-on` -- What's On Calendar (Bridge Console)

| Property     | Value                                                                        |
| ------------ | ---------------------------------------------------------------------------- |
| Layout       | `default`                                                                    |
| Compositions | Bridge Console — split month grid + sticky detail rail (collapses to drawer below `screen-lg`) |
| Auth         | Public (loyalty exclusives visible but marked as "members only")             |

**Sections (top to bottom)**

1. **Programme toolbar** (`BridgeProgrammeToolbar`) -- "— Programme · Vol XXIII" eyebrow + display-scale h1 (`What's <em>On</em>, May 2026` with italicized "On") on the left; Month/Week/List segmented control + prev / "Today · DD MMM" / next icon buttons on the right. Week and List are disabled in v1 with a "Coming soon" tooltip; the segmented control is built on `CvSegmentedControl`. Prev/next icon buttons use `CvIconButton`.
2. **Filter ribbon** (`BridgeFilterRibbon`) -- Six toggleable `CvChip`s (Showtimes, Specials, Members, Sensory, Captions, Audio Described) on the left; type-color legend (Special / Members / Sensory) on the right. Default = all six on. Rentals (`private_screening_blackout`) are always shown regardless of chip state. Chip state syncs to `?chips=...` (omitted at default); legacy `?type=` and `?accessibility=` URLs translate forward on first load. The chip set is the union of two backend axes — chip → backend mapping lives in `useBridgeFilters`.
3. **Bridge layout** -- Two-column grid above `screen-lg` (1fr / 26rem fixed):
   - **Month grid** (`BridgeMonthGrid`) -- 5- or 6-week month, Mon-start. Cells separated by 1px on a tinted background (no borders). Each `BridgeDayCell` shows the day number, up to 4 type-color flag dots, up to 2 event lines (time + title with type-color left border) and a `+N more` overflow row. States: default / hover / today (gold day number) / selected (`primary-container` fill, gold-40 inset outline) / muted (outside-month) / has-rental (135° corner-stripe). Roving tabindex; arrows / Home / End move focus.
   - **Detail rail** (`BridgeDetailRail`) -- Sticky `top: 5.5rem`. Composes three cards top-to-bottom: `BridgeDetailHero` (eyebrow + 4rem day numeral + hero film with 4-up showtime tile grid via the calendar event's embedded `showtimes` payload), `BridgeAlsoToday` (5-row max list with × badge for rentals), `BridgeCinemaReadout` (4-stat readout — static stub for v1).
4. **Detail drawer** (`BridgeDetailDrawer`) -- Below `screen-lg`, the rail collapses out of the grid and tapping a day cell opens a slide-up sheet that wraps the same three cards. Backdrop click and Escape close the drawer; focus is trapped inside the panel.

**Hero film selection** -- per the shared `pickHeroEvent` helper: prefer `special_event` or `loyalty_exclusive`, otherwise the first non-rental event of the day. Rentals never become the hero. Same logic feeds both the rail and the drawer.

**Default selected day** -- today if today is in the visible month, otherwise the 1st of that month. URL `?date=YYYY-MM-DD` overrides; clicking a day writes back to `?date=` (or strips it when the user lands back on today).

**Components**

`BridgeProgrammeToolbar`, `BridgeFilterRibbon`, `BridgeMonthGrid`, `BridgeDayCell`, `BridgeDetailRail`, `BridgeDetailHero`, `BridgeAlsoToday`, `BridgeCinemaReadout`, `BridgeMiniPoster`, `BridgeDetailDrawer`, `CvChip`, `CvSegmentedControl`, `CvIconButton`, `CvIcon`

**Data Requirements**

- `GET /api/calendar/events?month=M&year=Y` -- the page fetches the full visible month and applies chip filters client-side (chip toggles can't round-trip through the single-axis API filter). Synthesized `showtime`-type events carry an embedded `showtimes: Array<{ id; startTime; auditoriumLabel; soldOut }>` payload that powers the detail rail's tile grid without a second round-trip.

**SEO**

- Title: `What's On — Final Cut`
- Structured data: `Event` (deferred — current page emits `og:` tags only)

---

### `/events` -- Special Events Listing

| Property     | Value                                            |
| ------------ | ------------------------------------------------ |
| Layout       | `default`                                        |
| Compositions | Wide Frame (featured), Ensemble (upcoming)       |
| Auth         | Public                                           |

**Sections (top to bottom)**

1. **Featured Event** (Wide Frame) -- Hero-style spotlight for the next/most important event with full-bleed image, title, date, and CTA. Feels like a curated program, not a coupon book.
2. **Upcoming Events** -- Asymmetric grid with one large card and 2–3 smaller cards to create visual hierarchy. Each shows event image, date, title, description preview, and "Learn More" link.
3. **Past Events** (optional) -- Photo gallery of recent events to show the venue's personality and build social proof.

**Components**

`EventListCard`, `CvCard`, `CvBadge`

**Data Requirements**

- `GET /api/calendar/events?type=special_event`

**SEO**

- Title: `Events -- [Theater Name]`
- Structured data: `Event` (`ItemList`)

---

### `/events/:slug` -- Event Detail

| Property     | Value                                         |
| ------------ | --------------------------------------------- |
| Layout       | `default`                                     |
| Compositions | Wide Frame (hero), Close-Up (body)            |
| Auth         | Public                                        |

**Sections (top to bottom)**

1. **Wide Frame hero** -- Event image.
2. **Close-Up body** -- Title, date/time, description, what's included, pricing.
3. **CTA** -- "Get Tickets" or "RSVP" depending on event type.

**Components**

`EventDetail`, `CvButton`, `CvBadge`

**Data Requirements**

- `GET /api/calendar/events/:slug`

**SEO**

- Title: `[Event Name] -- [Theater Name]`
- Structured data: `Event`

---

## Nice-to-Have

### `/blog` -- Blog Listing

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `default`       |
| Compositions | Ensemble grid   |
| Auth         | Public          |

**Sections (top to bottom)**

1. Ensemble grid of blog post cards with featured image (16:9 thumbnail), title, excerpt, date, and author.

**Components**

`BlogPostCard`, `CvCard`

**Data Requirements**

- `@nuxt/content` `queryContent` for blog posts

**SEO**

- Title: `Blog -- [Theater Name]`
- Structured data: `Blog`

---

### `/blog/:slug` -- Blog Post

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `default`       |
| Compositions | Close-Up        |
| Auth         | Public          |

**Sections (top to bottom)**

1. Title, author, date.
2. Featured image.
3. Article body (rendered markdown).
4. Related posts.

**Components**

`BlogPostBody`, `BlogPostCard`

**Data Requirements**

- `@nuxt/content` `queryContent` by slug

**SEO**

- Title: `[Post Title] -- [Theater Name] Blog`
- Structured data: `Article`

---

### `/careers` -- Careers

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `default`       |
| Compositions | Close-Up        |
| Auth         | Public          |

**Sections (top to bottom)**

1. Intro text about working at the theater.
2. Current openings (title, department, type).
3. Benefits.
4. Application instructions or external ATS link.

**Components**

`CvCard`, `CvAccordion`, `CvButton`

**Data Requirements**

- Static or `@nuxt/content`

**SEO**

- Title: `Careers -- [Theater Name]`
- Structured data: `JobPosting`

---

### `/accessibility` -- Accessibility Statement

| Property     | Value           |
| ------------ | --------------- |
| Layout       | `default`       |
| Compositions | Close-Up        |
| Auth         | Public          |

**Sections (top to bottom)**

1. Commitment statement.
2. Assisted listening devices (how to request).
3. Wheelchair seating (locations, companion seats).
4. Open caption showtimes — schedule summary with direct link to calendar pre-filtered: `/whats-on?accessibility=open_caption`.
5. Audio description availability — link to calendar: `/whats-on?accessibility=audio_described`.
6. Sensory-friendly screenings — what's different (lights up, sound down), schedule summary with direct link: `/whats-on?accessibility=sensory_friendly`.
7. Service animal policy.
8. Contact for accommodation requests.

**Components**

`CvAccordion` (optional -- sections may be flat)

**Data Requirements**

- Static content

**SEO**

- Title: `Accessibility -- [Theater Name]`
