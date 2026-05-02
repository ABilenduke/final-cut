# Final Cut Documentation

Navigation index for all project documentation.

---

## Architecture

System design and technical decisions.

- [Site Architecture](architecture/SITE_ARCHITECTURE.md) — Overall app structure, routing, layouts, dependencies
- [Content Architecture](architecture/CONTENT_ARCHITECTURE.md) — Static-first cross-location content tier; location-at-intent pattern; geolocation-aware defaults; sitemap and SEO contract
- [Data Models](architecture/DATA_MODELS.md) — Database schema, TypeScript interfaces, API route inventory
- [State Management](architecture/STATE_MANAGEMENT.md) — Frontend state architecture (useState composables, no Pinia)

## Design System

Visual language, tokens, and implementation rules.

- [Design System](design-system/DESIGN_SYSTEM.md) — Creative vision, color tokens, typography, elevation, components
- [Design System Structure](design-system/DESIGN_SYSTEM_STRUCTURE.md) — Spacing, layout compositions, responsiveness, sizing, z-index, motion, accessibility
- [Design System Implementation](design-system/DESIGN_SYSTEM_IMPLEMENTATION.md) — Translating the design system into CSS and Vue

## Specs

Feature specifications and component contracts.

- [Component Inventory](specs/COMPONENT_INVENTORY.md) — Complete UI component catalog with props, slots, events, accessibility
- [Page Specs](specs/PAGE_SPECS.md) — Route-level specifications with layouts, compositions, data requirements
- [Purchase Flow](specs/PURCHASE_FLOW.md) — End-to-end ticket purchase journey (seat selection, checkout, confirmation)

## Plans

Implementation plans organized by stack and version.

- [Backend v1 Index](plans/backend/v1/00-index.md) — Plans 01-08 (all complete, 410 tests)
- [Backend Features](plans/backend/features/) — Standalone post-v1 feature plans
  - [Cross-Location Content API (2026-05-02)](plans/backend/features/2026-05-02-cross-location-content-api.md) — `/api/food-menu`, `/api/movies/{slug}/showtimes`, `/api/featured-slides`, locations field audit
- [Frontend v1 Index](plans/frontend/v1/00-index.md) — Plans 01-13 (Plan 13 adds the content-page refactor)
  - [Plan 13: Content Refactor](plans/frontend/v1/13-content-refactor.md) — Static-first cross-location content pages, hero carousel, `/locations`, geolocation defaults
- [Admin v1 Index](plans/admin/v1/00-index.md) — Plans 01-09 (Filament admin panel)
- [Admin Features](plans/admin/features/) — Standalone post-v1 admin feature plans
  - [Content Curation Admin (2026-05-02)](plans/admin/features/2026-05-02-content-curation-admin.md) — `FeaturedSlideResource`, MenuItem location-availability matrix, LocationResource hours field
- [Admin v1 Spec](superpowers/specs/2026-04-20-admin-section-design.md) — Source-of-truth design spec for the admin panel

## Progress

Execution journals tracking decisions, blockers, and file changes.

- [Backend v1 Progress](progress/backend-v1.md) — Plans 01-08 execution log
- [Frontend v1 Progress](progress/frontend-v1.md) — Plans 01-13 execution log (not yet started)
- [Admin v1 Progress](progress/admin-v1.md) — Plans 01-09 execution log

## Runbooks

Production operational procedures.

- [Admin Operations](runbooks/admin-operations.md) — Common admin-panel ops (create users, unban IPs, rotate passwords, emergency shutdown)
