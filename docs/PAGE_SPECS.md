# Page Specifications

Comprehensive specification for every page on the movie theatre website, grouped by implementation tier.

---

## Layouts

| Layout      | Description                                                        |
| ----------- | ------------------------------------------------------------------ |
| `default`   | Standard site layout with header (Neural Ticker), nav, and footer  |
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
| Compositions | Wide Frame (hero), Ensemble (now showing grid, what's on this week, coming soon grid) |
| Auth         | Public                                                                          |

**Sections (top to bottom)**

1. **Hero** -- Featured now-showing film with backdrop image, title, tagline, and "Get Tickets" CTA.
2. **Now Showing** -- Ensemble grid of movie cards displaying poster, title, rating badge, and next available showtime as a clickable time chip linking directly to `/purchase/:showtimeId`. The path from title → time → purchase should be a single click. This is the core funnel: what's playing → when → buy.
3. **What's On This Week** -- Compact event preview list for the current week. Moved above Coming Soon to surface times for people who know *when* they want to go but not *what*.
4. **Coming Soon** -- Ensemble grid of movie cards with a "Notify Me" action instead of a showtime.
5. **Neural Ticker** -- Ambient showtimes and events ticker rendered in the layout header.

**Components**

`MovieHero`, `MovieCard`, `EventListCard`

**Data Requirements**

- `GET /api/movies?status=now_showing`
- `GET /api/movies?status=coming_soon`
- `GET /api/calendar/events?range=week`

**SEO**

- Title: `[Theater Name] -- Now Showing & Tickets`
- Structured data: `ItemList` (Movie)

---

### `/movies` -- Movie Listings

| Property     | Value                        |
| ------------ | ---------------------------- |
| Layout       | `default`                    |
| Compositions | Ensemble grid                |
| Auth         | Public                       |

**Sections (top to bottom)**

1. **Tab bar / toggle** -- Now Showing and Coming Soon tabs.
2. **Filter controls** -- Genre and rating filters.
3. **Movie grid** -- Ensemble grid of movie cards.

**Components**

`MovieCard`, `CvBadge` (genre tags)

**Data Requirements**

- `GET /api/movies?status=now_showing` or `?status=coming_soon`
- Query params: `genre`, `rating`

**SEO**

- Title: `Now Showing -- [Theater Name]` or `Coming Soon -- [Theater Name]`
- Structured data: Movie

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
3. **Establishing Shot -- Right (35%)** -- Showtime selector with date tabs, time slots, and "Select Seats" CTA linking to `/purchase/:showtimeId`. Movie rating badge.

**Components**

`MovieHero` (backdrop variant), `MovieDetail`, `MovieTrailerEmbed`, `MovieCastList`, `MovieRatingBadge`, `ShowtimeSelector`, `CvBadge`

**Data Requirements**

- `GET /api/movies/:slug`
- `GET /api/movies/:slug/showtimes`

**SEO**

- Title: `[Movie Title] -- Showtimes & Tickets -- [Theater Name]`
- Structured data: `Movie` schema, `VideoObject` for trailer

---

### `/contact` -- Contact / Location / Hours

| Property     | Value                        |
| ------------ | ---------------------------- |
| Layout       | `default`                    |
| Compositions | Establishing Shot 65/35      |
| Auth         | Public                       |

**Sections (top to bottom)**

1. **Left (65%)** -- Embedded map with dark theme. Directions (driving, transit, walking). Parking info. Accessibility info (ramp locations, accessible parking).
2. **Right (35%)** -- Hours of operation table. Phone number and email. Contact form (name, email, subject, message).

**Components**

`ContactMap`, `ContactForm`, `CvInput`, `CvTextarea`, `CvButton`

**Data Requirements**

- Static content (hardcoded or from `app.config.ts`)
- `POST /api/contact` (form submission)

**SEO**

- Title: `Visit Us -- [Theater Name]`
- Structured data: `LocalBusiness`

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
3. Password change (current, new, confirm).
4. Save button.

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

1. **Movie title and showtime info bar.**
2. **Screen indicator** -- visual bar representing the screen position.
3. **Seat grid** -- Interactive grid with row labels pinned left, scrollable on mobile.
4. **Seat legend** -- Available, selected, taken, accessible, premium.
5. **Cart summary** -- Sidebar on desktop, bottom sheet on mobile. Shows selected seats and running total.
6. **"Continue to Checkout" CTA.**

**Components**

`AuditoriumGrid`, `AuditoriumSeat`, `AuditoriumScreenBar`, `AuditoriumLegend`, `CartSummary`, `CvButton`

**Data Requirements**

- `GET /api/showtimes/:id` (includes seat map and availability)

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

1. **Left (65%)** -- Order summary (movie, showtime, seats, prices). Food pre-order panel (optional food and drink add-ons from the menu). Promo code input. Gift card redemption input.
2. **Right (35%)** -- Checkout form with Stripe Elements (card input, billing). Guest email field if not logged in. "Complete Purchase" CTA.

**Components**

`CartSummary`, `FoodPreOrderPanel`, `PromoCode`, `CheckoutForm`, `CvInput`, `CvButton`

**Data Requirements**

- `GET /api/food-menu` (for pre-order panel)
- `POST /api/bookings` (creates PaymentIntent, validates seats, processes order)

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
| Compositions | Ensemble grid with tab bar         |
| Auth         | Public                             |

**Sections (top to bottom)**

1. **Category tabs** -- Popcorn, Drinks, Snacks, Combos, Specials.
2. **Menu grid** -- Ensemble grid of menu items showing image, name, description, price, and dietary badges (vegan, GF, contains nuts, etc.).

**Components**

`MenuCategoryTabs`, `MenuItem`, `CvBadge`, `CvCard`

**Data Requirements**

- `GET /api/food-menu`

**SEO**

- Title: `Food & Drink -- [Theater Name]`
- Structured data: `Menu`

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

### `/whats-on` -- What's On Calendar

| Property     | Value                                                                        |
| ------------ | ---------------------------------------------------------------------------- |
| Layout       | `default`                                                                    |
| Compositions | Wide Frame (calendar fills width)                                            |
| Auth         | Public (loyalty exclusives visible but marked as "members only")             |

**Sections (top to bottom)**

1. **Calendar controls** -- Month, week, and list view toggle. Date navigation (previous/next). Event type filter checkboxes (showtimes, special events, loyalty exclusives). Accessibility filter checkboxes in plain language: "Sensory Friendly", "Open Captions", "Audio Described". Filtered accessibility events display a visible `CvBadge` with the accessibility type. Multiple accessibility filters use comma-separated URL encoding: `?accessibility=sensory_friendly,open_caption`. This format is canonical across deep links, URL state, and the API.
2. **Calendar grid** -- Month grid with event indicator dots color-coded by type. Accessibility events show an additional icon indicator.
3. **Calendar event list** -- Events for the selected day, shown below or beside the grid.

**Components**

`CalendarGrid`, `CalendarDayCell`, `CalendarEventList`, `CalendarFilters`, `EventListCard`, `CvButton`

**Data Requirements**

- `GET /api/calendar/events?month=M&year=Y&type=filter`

**SEO**

- Title: `What's On -- [Theater Name]`
- Structured data: `Event`

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
