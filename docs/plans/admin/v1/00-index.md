# Admin Implementation Plans — Master Index

> **Project:** Final Cut Movie Theatre — Admin App
> **Stack:** Laravel 13, PHP 8.4, Filament 3, PostgreSQL, Redis, Pest
> **Methodology:** MoSCoW prioritization, dependency-first ordering, domain grouping, inline testing
> **Spec:** [`docs/superpowers/specs/2026-04-20-admin-section-design.md`](../../../superpowers/specs/2026-04-20-admin-section-design.md)

---

## Architecture

Filament 3 admin panel in a **separate top-level Laravel app** at `admin/`. Shares the `final_cut` PostgreSQL database and Redis cache with the backend API, but has its own:

- `admin_users` table with a `disabled_at` kill-switch column (no reuse of customer `users`)
- `admin` auth guard, session cookie scoped to `admin.finalcut.test` in dev and `admin.finalcut.com` in prod (no leading dot in either — see Plan 02 Task 2)
- Docker service, nginx vhost, deployment pipeline
- Eloquent model layer (mirrors the shared-domain schema via a parity tripwire test that now asserts cast parity)

**Shared-code boundary (ADR-001, Plan 03 Task 1).** Backend and admin share domain services, Eloquent models whose writes cross the admin/backend boundary, enums, and activity-log event classes via a **shared Composer package** at `packages/shared-domain/` (namespace `FinalCut\Domain\`), wired into both apps via a Composer path repository. No synthetic namespaces, no absolute-path classmaps, no read-only bind mounts of `./backend` into admin containers. Backend retains its HTTP controllers and customer-facing services outside the package; admin consumes the package as a normal Composer dependency.

Admin writes to shared tables **go through shared-domain services** (`MovieService`, `ShowtimeService`, `LoyaltyService`, `GiftCardService`, `PromoCodeService`, `AuditoriumService`) rather than direct Eloquent mutations. Every service write method takes an explicit `Causer` argument (`AdminUser` for admin calls, `User` for customer calls, `SystemCauser` for scheduled/webhook calls) so audit attribution is unambiguous across contexts. Enforcement is mechanical: phpstan `disallowedMethodCalls` + deptrac layer rules fail CI on direct mutations to shared-domain models from Filament Resources (Plan 03 Task 7).

Roles: **admin** (full), **manager** (content + operations, no financial mutations), **ops** (read-only support). No location scoping in v1.

---

## Plan Summary

| # | Plan | MoSCoW | Complexity | Depends On | Status |
|---|------|--------|------------|------------|--------|
| 01 | [Admin App Scaffold & Docker](01-admin-scaffold-and-docker.md) | Must Have | L | None | Pending |
| 02 | [Auth, Roles, Permissions & Audit Log](02-auth-roles-permissions-audit.md) | Must Have | M | 01 | Pending |
| 03 | [Shared Eloquent Models & Base Resources](03-shared-models-and-base-resources.md) | Must Have | M | 01, 02 | Pending |
| 04 | [Movie Catalog Management](04-movie-catalog-management.md) | Must Have | M | 03 | Pending |
| 05 | [Locations, Auditoriums & Seat Editor](05-locations-auditoriums-seat-editor.md) | Must Have | XL | 03 | Pending |
| 06 | [Showtime Management](06-showtime-management.md) | Must Have | XL | 04, 05 | Pending |
| 07 | [Bookings, Customers & Loyalty](07-bookings-customers-loyalty.md) | Should Have | M | 03, 06 | Pending |
| 08 | [Menu, Promo Codes & Gift Cards](08-menu-promo-gift-cards.md) | Should Have | M | 05 | Pending |
| 09 | [Calendar Events, Testing & Deploy](09-calendar-events-testing-deploy.md) | Should Have | L | 04–08 | Pending |

---

## Dependency Graph

```
01 Admin Scaffold & Docker
│
02 Auth, Roles, Permissions & Audit Log
│
03 Shared Eloquent Models & Base Resources
│
├── 04 Movie Catalog Management (Must)
│   └── 06 Showtime Management (Must)
│        └── 07 Bookings, Customers & Loyalty (Should)
│
├── 05 Locations, Auditoriums & Seat Editor (Must)
│   ├── 06 Showtime Management (also blocks on 05)
│   └── 08 Menu, Promo Codes & Gift Cards (Should)
│
└── 09 Calendar Events, Testing & Deploy (Should) ←── all domain plans
```

---

## Critical Path

The minimum viable admin — enough to run the business day-to-day — requires:

```
01 → 02 → 03 → 04 → 05 → 06
```

After Plan 06, staff can manage movies, locations, auditoriums, and showtimes. Plans 07–09 round out operations, merchandise, and events.

---

## Build Phases

### Phase 1: Foundation (Plans 01–03)
Admin app scaffold, authentication and authorization, shared Eloquent models with parity tripwire. Everything downstream depends on this.

### Phase 2: Core Catalog (Plans 04–06)
Movies, locations, auditoriums, seats, showtimes. The minimum staff needs to run the theatre.

### Phase 3: Operations & Merchandise (Plans 07–08)
Customer lookup, loyalty adjustments, menu management, promo codes, gift cards.

### Phase 4: Events, Testing & Deploy (Plan 09)
Calendar events, full Pest suite pass, production hardening (IP allowlist, rate limits, Fail2ban), CI wiring, docs.

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

- **Backend v1** (complete, 410+ tests) provides the shared database schema, domain models, and services that admin calls via the write-boundary rule.
- **Frontend v1** (in progress) is the customer-facing Nuxt app, entirely untouched by admin work.
- **Admin v1** is operationally independent — an admin bug cannot take down ticket sales.
