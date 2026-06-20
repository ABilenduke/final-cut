# Admin Implementation Plans — Master Index

> **Project:** Final Cut Movie Theatre — Admin Panel
> **Stack:** Laravel 13 (existing `backend/` app), PHP 8.4, Filament 5, PostgreSQL, Redis, Pest
> **Methodology:** MoSCoW prioritization, dependency-first ordering, domain grouping, inline testing
> **Spec:** [`docs/superpowers/specs/2026-04-20-admin-section-design.md`](../../../superpowers/specs/2026-04-20-admin-section-design.md)

---

## Architecture

Filament 5 admin panel installed **inside the existing `backend/` Laravel app** and served at a dedicated subdomain (`admin.finalcut.test` in dev, `admin.finalcut.com` in prod). There is no separate Laravel app, no new Docker service, no cross-app code bridge. Admin Resources, Pages, and the panel provider live under `backend/app/Filament/` and `backend/app/Providers/Filament/`. Admin-only tables (`admin_users`, Spatie permission tables, `activity_log`, `loyalty_adjustments`) are migrated via `backend/database/migrations/` alongside customer tables.

Isolation between the admin surface and the customer surface is enforced at three layers:

1. **Nginx vhost separation.** `admin.conf.template` and the existing `default.conf.template` are distinct server blocks. Both fastcgi to the same backend PHP-FPM container, but only the customer vhost can `proxy_pass` to the Nuxt frontend. The wildcard dev cert already covers `*.finalcut.test`.
2. **Laravel route-domain scoping.** Customer API routes are wrapped in `Route::domain(config('app.primary_domain'))->prefix('api')->group(...)`; Filament's `AdminPanelProvider` uses `->domain(config('filament.admin_domain'))`. A Pest `RouteDomainScopingTest` asserts no route leaks across domains.
3. **Session cookie scoping.** Admin session cookie `admin_session` scoped to the admin subdomain only. Customer session cookie scoped to the primary domain. Redis prefix `admin_session:` keeps the two session stores apart.

Admin writes to shared tables go through service classes in `backend/app/Services/` (`MovieService`, `ShowtimeService`, `AuditoriumService`, `LoyaltyService`, `GiftCardService`, `PromoCodeService`). Service methods accept an optional `?AdminUser $actor = null` for audit-log attribution when called from the admin panel. Services are shared between the customer API and the admin panel — not because of a cross-app boundary, but because there is one app and service extraction is good discipline. Filament Resources for pure-content fields without invariants (menu item description, calendar event notes) may write directly via Eloquent.

Roles: **admin** (full), **manager** (content + operations, no financial mutations), **ops** (read-only support). Roles are scoped to the `admin` guard via spatie/permission's `guard_name`, so customer users cannot be assigned admin roles. No location scoping in v1.

---

## Plan Summary

| # | Plan | MoSCoW | Complexity | Depends On | Status |
|---|------|--------|------------|------------|--------|
| 01 | [Admin Panel Scaffold & Nginx Vhost](01-admin-scaffold-and-docker.md) | Must Have | M | None | ✅ Complete (2026-04-22) |
| 02 | [Auth, Roles, Permissions & Audit Log](02-auth-roles-permissions-audit.md) | Must Have | M | 01 | ✅ Complete (2026-04-22) |
| 03 | [Base Resource Class & Loyalty Adjustments](03-shared-models-and-base-resources.md) | Must Have | S | 01, 02 | ✅ Complete (2026-04-22) |
| 04 | [Movie Catalog Management](04-movie-catalog-management.md) | Must Have | M | 03 | ✅ Complete (2026-04-23) |
| 05 | [Locations, Auditoriums & Seat Editor](05-locations-auditoriums-seat-editor.md) | Must Have | XL | 03 | ✅ Complete (2026-04-23) |
| 06 | [Showtime Management](06-showtime-management.md) | Must Have | XL | 04, 05 | ✅ Complete (2026-04-24) |
| 07 | [Bookings, Customers & Loyalty](07-bookings-customers-loyalty.md) | Should Have | M | 03, 06 | ✅ Complete (2026-04-24) |
| 08 | [Menu, Promo Codes & Gift Cards](08-menu-promo-gift-cards.md) | Should Have | M | 05 | ✅ Complete (2026-04-25) |
| 09 | [Calendar Events, Testing & Hardening](09-calendar-events-testing-deploy.md) | Should Have | L | 04–08 | ✅ Complete (2026-04-26) |

---

## Dependency Graph

```
01 Admin Panel Scaffold & Nginx Vhost
│
02 Auth, Roles, Permissions & Audit Log
│
03 Base Resource Class & Loyalty Adjustments
│
├── 04 Movie Catalog Management (Must)
│   └── 06 Showtime Management (Must)
│        └── 07 Bookings, Customers & Loyalty (Should)
│
├── 05 Locations, Auditoriums & Seat Editor (Must)
│   ├── 06 Showtime Management (also blocks on 05)
│   └── 08 Menu, Promo Codes & Gift Cards (Should)
│
└── 09 Calendar Events, Testing & Hardening (Should) ←── all domain plans
```

---

## Critical Path

The minimum viable admin — enough to run the business day-to-day — requires:

```
01 → 02 → 03 → 04 → 05 → 06
```

After Plan 06, staff can manage movies, locations, auditoriums, and showtimes. Plans 07–09 round out operations, merchandise, events, and production hardening.

---

## Build Phases

### Phase 1: Foundation (Plans 01–03)
Filament install, panel provider, nginx vhost, route-domain scoping, admin auth stack, roles & permissions, activity log, Base Resource class, loyalty_adjustments table.

### Phase 2: Core Catalog (Plans 04–06)
Movies, locations, auditoriums, seats, showtimes. The minimum staff needs to run the theatre.

### Phase 3: Operations & Merchandise (Plans 07–08)
Customer lookup, loyalty adjustments, menu management, promo codes, gift cards.

### Phase 4: Events, Testing & Hardening (Plan 09)
Calendar events, full Pest suite pass, production hardening (IP allowlist, rate limits, Fail2ban), docs updates.

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
| **Must Have** | Ship-blocking. Admin cannot launch without this. |
| **Should Have** | Important but not blocking. Can ship admin MVP without, but should follow quickly. |
| **Could Have** | Nice to have. Adds value but is deferrable. |
| **Won't Have** | Out of scope for this phase. |

---

## Scope Boundaries

### Deferred but High Priority Post-v1

- **MFA / 2FA for admin login** — biggest v1 security gap. Mitigated short-term by IP allowlist + Fail2ban; land as early v2 work.
- **Booking write operations** — cancel, refund (Stripe-integrated), seat modification. Read-only in v1; manual cancellation workflow in Plan 06 documents the interim process.
- **Per-location manager scoping** — all roles see all locations in v1. Likely needed for the two-location operation; retrofit cost tracked in spec § 2.7.

> **Post-v1 status (2026-06):** Several items below have since shipped in the admin v2–v5 rounds and are no longer deferred — booking write operations (cancel / refund / seat amendment via `BookingRefundService` + `BookingAmendmentService`), admin-managed blog posts (`BlogPostResource`), and broad CMS-managed site content. **MFA / 2FA** and **per-location manager scoping** remain the open post-v1 items.

### Won't Have (out of scope indefinitely)

- Customer impersonation
- Bulk CSV import / export
- Admin-managed blog posts (blog stays in `frontend/app/data/blog.ts` until v2)
- Rate limiting beyond login endpoint (VPN / IP allowlist handles broader admin access)
- Multi-tenancy / white-label support

---

## Progress Tracking

Execution journal: `docs/progress/admin-v1.md` (scaffolded in Plan 01, updated per-step per project convention).

---

## Relationship to Backend & Frontend Plans

- **Backend v1** (complete, 410+ tests) provides the shared database schema, the existing service layer (`TmdbService`, `SeatAvailabilityService`, `StripeService`, `LoyaltyService`), and the Eloquent models that Filament Resources reuse directly. New services extracted for admin (`MovieService`, `ShowtimeService`, `AuditoriumService`, `GiftCardService`, `PromoCodeService`) land in `backend/app/Services/` and are available to customer API endpoints as well.
- **Frontend v1** (complete) is the customer-facing Nuxt app, entirely untouched by admin work.
- **Admin v1** lives inside `backend/` but is isolated at the network edge (subdomain + route-domain scoping) so admin routes never answer on the customer domain and vice versa.
