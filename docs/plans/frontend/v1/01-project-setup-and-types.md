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

    // Route rules (ISR/SSR/prerender) deferred to Plan 13 (E2E & Polish).
    // Target values documented in docs/architecture/SITE_ARCHITECTURE.md.
    // For v1, all routes use default client-side rendering.

    runtimeConfig: {
      public: {
        apiBaseUrl: '',              // Laravel API base URL (NUXT_PUBLIC_API_BASE_URL)
        stripePublishableKey: '',    // Stripe publishable key (client-side only)
        siteUrl: '',                 // Base URL for SEO, OG tags
      },
    },

    modules: ['@nuxt/fonts'],

    fonts: {
      families: [
        { name: 'Noto Serif', weights: [400, 700] },
        { name: 'Newsreader', weights: [400, 700], styles: ['normal', 'italic'] },
      ],
    },
  })
  ```

- **Acceptance Criteria:**
  - [ ] Route rules deferred to Plan 13 (comment placeholder present in config)
  - [ ] Runtime config keys match environment variable spec (no sessionPassword)
  - [ ] `@nuxt/fonts` installed and configured with Noto Serif + Newsreader
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
  - `frontend/app/types/location.ts` — `Location`
- **Details:**
  Copy interfaces exactly from DATA_MODELS.md Section 1. All interfaces are `export`ed. Nuxt auto-imports from `app/types/` so no barrel file needed.

  ```typescript
  // app/types/location.ts
  export interface Location {
    id: string
    name: string
    slug: string
    address: string
  }
  ```
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
  - `frontend/app/data/promoCodes.ts` — Client-side promo code preview config
- **Details:**
  Typed arrays using the interfaces from Task 2. FAQ has 5 categories (Tickets & Booking, Age Restrictions & Ratings, Accessibility, Food & Allergies, Policies) with 3-5 items each. Menu has items across all 5 categories (popcorn, drinks, snacks, combos, specials) with realistic data.

  Promo codes mirror `backend/config/promo_codes.php` for client-side discount preview. This is strictly non-authoritative — the server is the sole authority at booking time. Never assume parity with backend rules in logic or tests.
- **Acceptance Criteria:**
  - [ ] FAQ data satisfies `Array<{ category: string; items: Array<{ question: string; answer: string }> }>`
  - [ ] Menu data satisfies `Array<MenuItem>`
  - [ ] At least 5 FAQ categories with 3+ items each
  - [ ] At least 15 menu items across all categories
  - [ ] Data imports without type errors
  - [ ] Promo code data mirrors backend config structure
  - [ ] Data includes type (percentage/fixed), value, and max_discount

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
  - `@stripe/stripe-js` — Client-side Stripe Elements for PCI-compliant card collection

  nuxt-auth-utils deferred for v1 — SSR hydration not needed until SSR is enabled (Plan 13+). Auth state restored via `GET /api/auth/me` on app init.

  Dev dependencies (for later plans but install now):
  - `@nuxt/test-utils` — Component testing utilities

  @nuxt/content deferred to Plan 11 (post-MVP).

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

1. **@nuxt/content** — Explicitly deferred to Plan 11. Blog and FAQ markdown content will use static TypeScript data files until then.
2. **Google Fonts loading strategy** — Uses `@nuxt/fonts` module, which automatically downloads and self-hosts Google Fonts at build time. No external runtime dependency on Google CDN. Fonts are preloaded with `font-display: swap` by default.
3. **CORS for Sanctum** — The Laravel backend must be configured with `supports_credentials: true` and the frontend origin in `allowed_origins` for cookie-based auth to work. Verify during Plan 05 integration.
4. **CSRF bootstrap** — The Laravel backend uses Sanctum SPA auth. The frontend must call `GET /sanctum/csrf-cookie` before the first state-changing request. This is implemented in Plan 05 (API client layer).
