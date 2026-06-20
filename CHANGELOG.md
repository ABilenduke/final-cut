# Changelog

All notable changes to Final Cut are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Documentation

- Documentation accuracy sync. Corrected stale status markers (Frontend v1 and Admin v1 are complete, not "Pending"/"not yet started"; admin stack is Filament 5, not 3) and brought the architecture/spec reference lists in line with the code: the full backend service layer, all Filament resources and pages, the content/editorial composables, the `routeRules` map (incl. `/blog`, `/private-screenings`, `/terms`, `/privacy` and the `/whats-on` routeRule gap), the API route inventory (`ticker-items`, `blog-posts`, `site-content/gift-cards`, and the now-shipped Stripe webhook), and the component catalog (the `home/` tier plus the purchase-flow, concessions, gift-card and locations redesign components).

## [1.1.0] - 2026-06-20

### Added

- **`useSeo()` composable** (`frontend/app/composables/useSeo.ts`) and pure, unit-tested SEO builders (`frontend/app/utils/seo.ts`: `absoluteUrl`, `organizationSchema`, `eventSchema`, `buildSeoHead`) — a single source for each page's canonical link, Open Graph / Twitter meta, og:image fallback, and JSON-LD.
- **Site-wide SEO defaults** in `app.vue`: an idempotent title template (brands bare page titles with `— Final Cut`, never double-brands), default Open Graph / Twitter tags, a `public/og-default.png` social-share fallback, and brand-level `Organization` JSON-LD emitted once.
- **`schema.org` `Event` structured data** on `/events/:slug` (with a `Place` for venue-scoped events), plus canonical + `ItemList` on the `/events` listing.
- **Structured `location`** (name, address, geo) on the calendar-event detail API (`GET /api/calendar/events/:slug`) — exposed on the detail endpoint only (the month listing stays N+1-free) so Event JSON-LD is eligible for Google rich results.

### Changed

- Corrected SEO documentation: `SITE_ARCHITECTURE.md` and `CONTENT_ARCHITECTURE.md` now describe the hand-rolled Nitro sitemap/robots routes (the previously-claimed `@nuxtjs/sitemap` was removed earlier); added a `SEO` section and updated `PAGE_SPECS.md`.

- Brand wordmark logo in the site header (`public/final-cut-logo-wordmark.webp`), replacing the placeholder text wordmark, on a taller header bar (`--layout-header-height` 4rem → 5.5rem; logo 4rem) for prominence. Source art was optimized from a 5.1 MB PNG to a 48 KB WebP (~99% smaller) to keep the per-page header asset light.

### Fixed

- **Movie cards showed `NaNh NaNm` instead of the runtime** (home page and `/movies` listings). The list endpoint's `MovieListResource` omitted the `runtime` field, so the card formatted `undefined`. Added `runtime` to the list resource and hardened `MovieCard` to hide the runtime label for movies with no runtime (e.g. not-yet-enriched).
- **Checkout: payment was unreachable on mobile.** The Confirm & Pay button lived in the totals rail, which is hidden below `60rem`, so phone users could not complete a booking. Moved Confirm & Pay and the terms-consent agreement ("I agree to the ticketing terms and the auditorium policy. No late entry after 10 minutes; phones silenced and stowed.") out of the rail and out of the promo bay into a dedicated `CheckoutConfirmBay` section below the inputs (visible on every viewport, with the order total on the button). The totals rail is now a sticky summary so the order total stays visible while scrolling on desktop.
- Corrected the brand "established" year from `est. 2003` to `est. 2026` in the site header and the gift-card visual.

## [1.0.1] - 2026-06-19

### Fixed

- Production nginx OCSP-stapling template mount and the release health-gate endpoint; menu-item image path prefix for CDN delivery.

## [1.0.0] - 2026-06-19

### Added

- Initial production release: GHCR + SSH release pipeline deploying to the DigitalOcean droplet, TMDB attribution, and the full Final Cut customer-facing and Filament admin application.

[Unreleased]: https://github.com/ABilenduke/final-cut/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/ABilenduke/final-cut/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/ABilenduke/final-cut/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/ABilenduke/final-cut/releases/tag/v1.0.0
