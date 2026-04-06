# Plan 13: E2E Testing & Integration Polish

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plans 06–10 (all domain plans — pages must exist to test)
> **Unlocks:** None (final plan)

## Overview

Set up Playwright for end-to-end testing and build comprehensive test suites covering critical user paths, navigation, accessibility, and responsive behavior. This plan also addresses SEO verification and any integration polish discovered during testing.

## Reference Documents

- `docs/PURCHASE_FLOW.md` — Critical purchase path to test
- `docs/SITE_ARCHITECTURE.md` — Route map, rendering strategies
- `docs/PAGE_SPECS.md` — All page specs (what to verify)

---

## Tasks

### Task 1: Playwright Configuration

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/playwright.config.ts`
  - `frontend/tests/e2e/fixtures/` — Test fixtures and helpers
  - `frontend/tests/e2e/helpers/auth.ts` — Authentication helper
- **Details:**
  Configure Playwright with:
  - Base URL pointing to dev server
  - Browser projects: Chromium, Firefox, WebKit (mobile Safari)
  - Viewport presets: Desktop (1280×800), Tablet (768×1024), Mobile (375×812)
  - Authentication helper: login as test user, store session for reuse
  - Screenshot on failure
  - Test timeout: 30 seconds
  - Parallel execution

- **Acceptance Criteria:**
  - [ ] Playwright runs against dev server
  - [ ] Tests execute across 3 browsers
  - [ ] Auth helper logs in and stores session
  - [ ] Screenshots captured on failure

---

### Task 2: Critical Path E2E Suite (Revenue Path)

- **MoSCoW:** Must Have
- **Complexity:** L
- **Files:**
  - `frontend/tests/e2e/purchase-flow.spec.ts`
- **Details:**
  The most important test suite. Tests the complete revenue path:

  1. **Home → Movie Detail:** Navigate from home page, click movie card, arrive at detail page
  2. **Movie Detail → Seat Selection:** Click showtime, arrive at `/purchase/:showtimeId`
  3. **Seat Selection:** Select 2 seats, verify cart updates, click "Continue to Checkout"
  4. **Checkout:** Verify order summary, fill Stripe test card (4242...), complete purchase
  5. **Confirmation:** Verify booking code displays, QR code renders, .ics download works

  **Additional scenarios:**
  - Guest checkout (provide email)
  - Seat limit enforcement (try to select >10)
  - Empty cart redirect (navigate directly to checkout without seats)

- **Acceptance Criteria:**
  - [ ] Full purchase flow completes end-to-end
  - [ ] Stripe test payment processes
  - [ ] Confirmation page shows all booking details
  - [ ] Guest checkout works with email input
  - [ ] Edge cases handled (empty cart, seat limit)

---

### Task 3: Navigation E2E Suite

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/tests/e2e/navigation.spec.ts`
- **Details:**
  Test all page transitions and navigation patterns:
  - Header nav links navigate to correct pages
  - Mobile nav (bottom bar) works below breakpoint
  - Layout switches: default → purchase → blank → account
  - Back button behavior through purchase flow
  - Deep links work (direct URL to movie detail, filtered calendar)
  - 404 handling for invalid slugs

- **Acceptance Criteria:**
  - [ ] All header nav links work
  - [ ] Mobile nav items navigate correctly
  - [ ] Layout transitions are seamless
  - [ ] Deep links render correct content

---

### Task 4: Accessibility E2E Suite

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/tests/e2e/accessibility.spec.ts`
- **Details:**
  Test keyboard navigation and screen reader compatibility:
  - **Skip nav:** Tab to reveal, Enter to jump to main content
  - **Seat selection grid:** Full keyboard navigation (arrows, Enter, Escape, Tab)
  - **Modal:** Focus trap, Escape to close, focus return
  - **Accordion:** Enter/Space to toggle
  - **Calendar grid:** Arrow keys, Page Up/Down
  - **Landmark structure:** Verify banner, navigation, main, contentinfo roles
  - **Focus management:** Focus moves correctly on page transitions, modal open/close

  Optionally integrate `@axe-core/playwright` for automated WCAG checks.

- **Acceptance Criteria:**
  - [ ] Skip nav keyboard flow works
  - [ ] Seat grid keyboard navigation complete
  - [ ] Modal focus trap verified
  - [ ] Landmark roles present on all pages
  - [ ] No critical axe-core violations on key pages

---

### Task 5: Responsive E2E Suite

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/tests/e2e/responsive.spec.ts`
- **Details:**
  Test key pages at 3 viewport sizes (mobile, tablet, desktop):
  - Home page: hero, movie grid layout
  - Movie detail: Establishing Shot collapses to single column
  - Seat selection: horizontal scroll on mobile, sidebar on desktop
  - Checkout: 65/35 on desktop, single column on mobile
  - Account: sidebar → icon rail → bottom bar

- **Acceptance Criteria:**
  - [ ] Layouts respond correctly at all breakpoints
  - [ ] Mobile nav visible only below breakpoint
  - [ ] Seat grid scrolls horizontally on mobile
  - [ ] No horizontal overflow on any page at mobile width

---

### Task 6: SEO Verification

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/tests/e2e/seo.spec.ts`
- **Details:**
  Verify structured data and meta tags on ISR/prerendered pages:
  - Home: `ItemList` (Movie)
  - Movie detail: `Movie`, `VideoObject`
  - FAQ: `FAQPage`
  - Contact: `LocalBusiness`
  - Events: `Event`
  - Blog: `Article`
  - Title tags match PAGE_SPECS.md
  - `noindex` on auth, account, purchase pages
  - OG tags present on public pages

- **Acceptance Criteria:**
  - [ ] Structured data validates (check JSON-LD in page source)
  - [ ] Title tags match spec
  - [ ] `noindex` correctly applied
  - [ ] OG tags render for social sharing

---

## Testing Requirements

- **CI Integration:** Playwright tests should run in CI (GitHub Actions or similar)
- **Test Data:** Use mock data mode (`MOCK_DATA=true`) for consistent test data
- **Flakiness:** Avoid time-dependent tests. Use Playwright's built-in waiting/assertions instead of `setTimeout`

## Dependencies Map

```
Task 1 (Configuration) ← foundational
Task 2 (Purchase Flow) ← most critical, run first
Task 3 (Navigation) ← can run in parallel with 2
Task 4 (Accessibility) ← depends on components being built
Task 5 (Responsive) ← can run in parallel
Task 6 (SEO) ← can run in parallel
```

## Risks & Open Questions

1. **Stripe in tests** — Playwright tests need to interact with Stripe Elements (which is an iframe). Stripe provides test card numbers but the iframe interaction requires special handling. May need to mock the Stripe Elements for faster, more reliable tests.
2. **Docker test environment** — Tests run in a Playwright Docker container per the docker-compose.override.yml. Verify the container can reach the Nuxt dev server.
3. **ISR testing** — ISR pages serve cached content. Tests should verify the initial render, not worry about cache freshness.
4. **Test data consistency** — Mock data must be stable across test runs. Use committed JSON fixtures, not randomly generated data.
