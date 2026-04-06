# Plan 01: Project Setup & TypeScript Types

> **Priority:** Must Have
> **Complexity:** S
> **Depends On:** None — foundational
> **Unlocks:** Plans 02, 03, 04, 05 (everything)

## Overview

Configure the Nuxt project for production use and define all TypeScript interfaces that serve as the contract between frontend components, composables, server routes, and the backend API. This plan also covers utility functions, static data files, middleware stubs, and the toast plugin.

## Reference Documents

- `docs/SITE_ARCHITECTURE.md` — Project structure, route rules, environment variables, dependencies
- `docs/DATA_MODELS.md` — All TypeScript interfaces (Section 1), utility function signatures
- `docs/STATE_MANAGEMENT.md` — Composable interface contracts

---

## Tasks

### Task 1: Update `nuxt.config.ts`

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/nuxt.config.ts` — update existing
- **Details:**
  Configure route rules, runtime config, CSS entry point, Google Fonts, and app metadata per SITE_ARCHITECTURE.md.

  ```typescript
  export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',
    devtools: { enabled: true },

    css: ['~/assets/css/main.css'],

    routeRules: {
      '/':              { isr: 1800 },
      '/movies':        { isr: 1800 },
      '/movies/**':     { isr: 600 },
      '/food-drink':    { isr: 1800 },
      '/whats-on':      { isr: 900 },
      '/events':        { isr: 900 },
      '/blog/**':       { isr: 600 },
      '/contact':       { prerender: true },
      '/faq':           { prerender: true },
      '/accessibility':  { prerender: true },
      '/careers':       { prerender: true },
      '/purchase/**':   { ssr: false },
      '/account/**':    { ssr: false },
      '/auth/**':       { ssr: false },
    },

    runtimeConfig: {
      tmdbApiKey: '',
      stripeSecretKey: '',
      sessionPassword: '',
      public: {
        stripePublishableKey: '',
        siteUrl: '',
      },
    },

    app: {
      head: {
        link: [
          { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
          { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
          { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,700;1,400&family=Noto+Serif:wght@400;700&display=swap' },
        ],
      },
    },
  })
  ```

- **Acceptance Criteria:**
  - [ ] Route rules match the table in SITE_ARCHITECTURE.md
  - [ ] Runtime config keys match environment variable spec
  - [ ] Google Fonts (Noto Serif + Newsreader) load in both dev and production
  - [ ] CSS entry point registered (creates placeholder `main.css` if needed)
  - [ ] `npx nuxt dev` starts without errors

---

### Task 2: TypeScript Interfaces

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/types/movie.ts` — `Movie`, `Genre`, `CastMember`
  - `frontend/app/types/showtime.ts` — `Showtime`
  - `frontend/app/types/auditorium.ts` — `Auditorium`, `AuditoriumRow`, `AuditoriumSection`, `Seat`
  - `frontend/app/types/booking.ts` — `Booking`, `BookingSeat`, `BookingFoodItem`
  - `frontend/app/types/calendar-event.ts` — `CalendarEvent`, `AccessibilityTag`
  - `frontend/app/types/menu-item.ts` — `MenuItem`, `Allergen`, `DietaryTag`
  - `frontend/app/types/user.ts` — `User`, `UserProfile`
  - `frontend/app/types/gift-card.ts` — `GiftCard`
  - `frontend/app/types/rental-inquiry.ts` — `RentalInquiry`
- **Details:**
  Copy interfaces exactly from DATA_MODELS.md Section 1. All interfaces are `export`ed. Nuxt auto-imports from `app/types/` so no barrel file needed.
- **Acceptance Criteria:**
  - [ ] Every interface from DATA_MODELS.md Section 1 exists in the corresponding file
  - [ ] All type aliases (`AccessibilityTag`, `Allergen`, `DietaryTag`) are exported
  - [ ] TypeScript compilation passes with `npx nuxt typecheck` (or `npx nuxi typecheck`)
  - [ ] Types are auto-importable in components without explicit import statements

---

### Task 3: Utility Functions

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/utils/formatCurrency.ts` — Format cents to dollar string (e.g., `1299` → `"$12.99"`)
  - `frontend/app/utils/formatDate.ts` — Format ISO string to readable date (e.g., `"Apr 3, 2026"`)
  - `frontend/app/utils/formatRuntime.ts` — Format minutes to `"2h 34m"` or `"1h 45m"`
  - `frontend/app/utils/slugify.ts` — Convert title to URL-safe slug
  - `frontend/app/utils/seatLabel.ts` — Format seat ID for display (e.g., `"A1"` → `"Row A, Seat 1"`)
- **Details:**
  Pure functions, no side effects. Use `rem` convention in any UI-facing output. Currency uses `Intl.NumberFormat`. Date uses `Intl.DateTimeFormat`.
- **Acceptance Criteria:**
  - [ ] `formatCurrency(1299)` returns `"$12.99"`
  - [ ] `formatCurrency(0)` returns `"$0.00"`
  - [ ] `formatRuntime(154)` returns `"2h 34m"`
  - [ ] `formatRuntime(45)` returns `"45m"`
  - [ ] `slugify("The Dark Knight")` returns `"the-dark-knight"`
  - [ ] `seatLabel("A1")` returns `"Row A, Seat 1"`
  - [ ] All utilities are auto-imported by Nuxt

---

### Task 4: Static Data Files

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/data/faq.ts` — FAQ categories and items
  - `frontend/app/data/menu.ts` — Food/drink menu items
- **Details:**
  Typed arrays using the interfaces from Task 2. FAQ has 5 categories (Tickets & Booking, Age Restrictions & Ratings, Accessibility, Food & Allergies, Policies) with 3-5 items each. Menu has items across all 5 categories (popcorn, drinks, snacks, combos, specials) with realistic data.
- **Acceptance Criteria:**
  - [ ] FAQ data satisfies `Array<{ category: string; items: Array<{ question: string; answer: string }> }>`
  - [ ] Menu data satisfies `Array<MenuItem>`
  - [ ] At least 5 FAQ categories with 3+ items each
  - [ ] At least 15 menu items across all categories
  - [ ] Data imports without type errors

---

### Task 5: Route Middleware

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/middleware/auth.ts` — Protect `/account/**` routes
  - `frontend/app/middleware/guest.ts` — Redirect authenticated users from `/auth/**`
- **Details:**
  Per SITE_ARCHITECTURE.md: `auth.ts` checks `useAuth().isAuthenticated` and redirects to `/auth/login?redirect=<current-path>` if false. `guest.ts` checks `useAuth().isAuthenticated` and redirects to `/account` if true. Both are named middleware (not global) — applied via `definePageMeta` on relevant pages.
- **Acceptance Criteria:**
  - [ ] `auth` middleware redirects unauthenticated users to `/auth/login` with `redirect` query param
  - [ ] `guest` middleware redirects authenticated users to `/account`
  - [ ] Neither middleware crashes when `useAuth` composable is not yet implemented (stub-safe)

---

### Task 6: Toast Plugin

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/plugins/toast.client.ts` — Client-only plugin for toast initialization
- **Details:**
  Minimal plugin that ensures the toast composable state is available client-side. The actual toast rendering is handled by the CvToast component in the default layout. This plugin just ensures the `useToast` composable initializes correctly on the client.
- **Acceptance Criteria:**
  - [ ] Plugin only runs on client (`.client.ts` suffix)
  - [ ] Does not error during SSR
  - [ ] Toast queue is accessible after plugin initialization

---

### Task 7: Install Dependencies

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/package.json` — Add dependencies
- **Details:**
  Install required packages per SITE_ARCHITECTURE.md:
  - `nuxt-auth-utils` — Session-based authentication
  - `@stripe/stripe-js` — Client-side Stripe Elements
  - `stripe` — Server-side Stripe SDK

  Dev dependencies (for later plans but install now):
  - `@nuxt/test-utils` — Component testing utilities
  - `@nuxt/content` — Blog/FAQ markdown content (Plan 11)

- **Acceptance Criteria:**
  - [ ] All packages install without conflicts
  - [ ] `npx nuxt dev` starts successfully with new dependencies
  - [ ] No peer dependency warnings for core packages

---

## Testing Requirements

- **Unit Tests:** Tests for all 5 utility functions (formatCurrency, formatDate, formatRuntime, slugify, seatLabel) covering edge cases (zero values, empty strings, boundary values)
- **Type Check:** `npx nuxt typecheck` passes with zero errors
- **Dev Server:** Application starts and renders without errors

## Dependencies Map

```
Task 7 (Install Deps)
  └── Task 1 (Nuxt Config) ← needs deps installed first
        └── Task 2 (Types) ← config references CSS that needs types
              ├── Task 3 (Utilities)
              ├── Task 4 (Data Files) ← needs MenuItem type
              └── Task 5 (Middleware) ← needs User type (via useAuth)
Task 6 (Plugin) — independent, can run in parallel with 2-5
```

## Risks & Open Questions

1. **nuxt-auth-utils compatibility** — Verify it supports Nuxt 4. If not, may need to implement session management manually using `h3` session utilities.
2. **@nuxt/content** — May defer installation to Plan 11 if it causes conflicts with the current Nuxt version.
3. **Google Fonts loading strategy** — Consider using `@nuxt/fonts` module instead of raw `<link>` tags for better performance. Decision: start with `<link>` tags (simpler), optimize later if needed.
