# Frontend Implementation Plans — Master Index

> **Project:** Final Cut Movie Theatre
> **Stack:** Nuxt 4, Vue 3, TypeScript, CSS Custom Properties
> **Methodology:** MoSCoW prioritization, dependency-first ordering, domain grouping, inline testing

---

## Plan Summary

| # | Plan | MoSCoW | Complexity | Depends On | Status |
|---|------|--------|------------|------------|--------|
| 01 | [Project Setup & Types](01-project-setup-and-types.md) | Must Have | S | None | Pending |
| 02 | [Design System CSS](02-design-system-css.md) | Must Have | M | 01 | Pending |
| 03 | [UI Primitives](03-ui-primitives.md) | Must Have | L | 02 | Pending |
| 04 | [Layouts & Shell](04-layouts-and-shell.md) | Must Have | M | 02, 03 | Pending |
| 05 | [Composables & API Integration](05-composables-and-server-routes.md) | Must Have | L | 01, 02 | Pending |
| 06 | [Movie Domain](06-movie-domain.md) | Must Have | L | 03, 04, 05 | Pending |
| 07 | [Calendar & Events Domain](07-calendar-events-domain.md) | Should Have | L | 03, 04, 05 | Pending |
| 08 | [Purchase Flow Domain](08-purchase-flow-domain.md) | Must Have | XL | 03, 04, 05, 06 | Pending |
| 09 | [Auth & Account Domain](09-auth-account-domain.md) | Should Have | M | 03, 04, 05 | Pending |
| 10 | [Content Domain](10-content-domain.md) | Should Have | M | 03, 04, 05 | Pending |
| 11 | [Blog & Static Pages](11-blog-and-static-pages.md) | Could Have | S | 03, 04 | Pending |
| 12 | [Storybook Comprehensive](12-storybook-comprehensive.md) | Should Have | M | 03–10 | Pending |
| 13 | [E2E & Polish](13-e2e-and-polish.md) | Should Have | M | 06–10 | Pending |

---

## Dependency Graph

```
01 Project Setup & Types ─────────────────────────────┐
│                                                      │
02 Design System CSS                                   │
│                                                      │
03 UI Primitives ──────────────────────────────────────┤
│                                                      │
04 Layouts & Shell                                     │
│                                                      │
05 Composables & API Integration ──────────────────────┘
│
├── 06 Movie Domain (Must)
│   └── 08 Purchase Flow Domain (Must)
│
├── 07 Calendar & Events Domain (Should)
│
├── 09 Auth & Account Domain (Should)
│
├── 10 Content Domain (Should)
│
└── 11 Blog & Static Pages (Could)

12 Storybook Comprehensive ←── all component plans
13 E2E & Polish ←── all domain plans
```

---

## Critical Path (MVP)

The minimum viable product requires the revenue-generating purchase flow:

```
01 → 02 → 03 → 04 → 05 → 06 → 08
```

This path delivers: project config, design tokens, UI primitives, layouts, data layer, movie browsing, and ticket purchasing.

---

## Build Phases

### Phase 1: Foundation (Plans 01–05)
Everything downstream depends on this. Types, tokens, primitives, layouts, and data composables.

### Phase 2: Core Revenue Path (Plan 06, 08)
Movie browsing and ticket purchase — the application's reason to exist.

### Phase 3: Supporting Domains (Plans 07, 09, 10)
Calendar, auth/account, and content pages that round out the experience.

### Phase 4: Nice-to-Have & Polish (Plans 11–13)
Blog, comprehensive Storybook coverage, and end-to-end test suite.

---

## Complexity Guide

| Size | Meaning | Approximate Scope |
|------|---------|-------------------|
| XS | Trivial | Single file change, < 1 hour |
| S | Small | 2–5 files, < half day |
| M | Medium | 5–15 files, ~1 day |
| L | Large | 15–30 files, 2–3 days |
| XL | Extra Large | 30+ files, 3–5 days |

---

## MoSCoW Definitions

| Priority | Meaning |
|----------|---------|
| **Must Have** | Ship-blocking. The app cannot launch without this. |
| **Should Have** | Important but not blocking. Can ship an MVP without, but should follow quickly. |
| **Could Have** | Nice to have. Adds value but is deferrable. |
| **Won't Have** | Out of scope for this phase. |

---

## Won't Have (This Phase)

- WebSocket real-time seat updates (polling first)
- Server-sent events for ticker data
- Admin panel / CMS
- Email sending (stubs only)
- PWA / offline support
- i18n / localization
- Analytics integration
- A/B testing infrastructure
