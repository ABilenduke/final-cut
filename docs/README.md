# Final Cut Documentation

Navigation index for all project documentation.

---

## Architecture

System design and technical decisions.

- [Site Architecture](architecture/SITE_ARCHITECTURE.md) — Overall app structure, routing, layouts, dependencies
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
- [Frontend v1 Index](plans/frontend/v1/00-index.md) — Plans 01-13 (not yet started)

## Progress

Execution journals tracking decisions, blockers, and file changes.

- [Backend v1 Progress](progress/backend-v1.md) — Plans 01-08 execution log
- [Frontend v1 Progress](progress/frontend-v1.md) — Plans 01-13 execution log (not yet started)
