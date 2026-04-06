# Backend Implementation Plans — Master Index

> **Project:** Final Cut Movie Theatre
> **Stack:** Laravel 13, PHP 8.4, PostgreSQL, Redis, Pest
> **Methodology:** MoSCoW prioritization, dependency-first ordering, domain grouping, inline testing

---

## Plan Summary

| # | Plan | MoSCoW | Complexity | Depends On | Status |
|---|------|--------|------------|------------|--------|
| 01 | [Project Setup & Config](01-project-setup-and-config.md) | Must Have | S | None | Complete |
| 02 | [Database Schema](02-database-schema.md) | Must Have | L | 01 | Complete |
| 03 | [Movie API](03-movie-api.md) | Must Have | M | 02 | Complete |
| 04 | [Booking API](04-booking-api.md) | Must Have | XL | 02, 03 | Complete |
| 05 | [Auth API](05-auth-api.md) | Must Have | M | 02 | Complete |
| 06 | [Account API](06-account-api.md) | Should Have | M | 02, 05 | Complete |
| 07 | [Calendar & Content API](07-calendar-content-api.md) | Should Have | M | 02 | Complete |
| 08 | [Testing & Seeding](08-testing-and-seeding.md) | Should Have | M | 03–07 | Complete |

---

## Dependency Graph

```
01 Project Setup & Config
│
02 Database Schema
│
├── 03 Movie API (Must)
│   └── 04 Booking API (Must)
│
├── 05 Auth API (Must)
│   └── 06 Account API (Should)
│
└── 07 Calendar & Content API (Should)

08 Testing & Seeding ←── all API plans
```

---

## Critical Path (MVP)

```
01 → 02 → 03 → 04
```

This path delivers: project config, database schema, movie/showtime endpoints, and the booking/payment flow.

---

## Build Phases

### Phase 1: Foundation (Plans 01–02)
Project configuration and the complete database schema with models, migrations, factories, and seeders.

### Phase 2: Core Revenue Path (Plans 03–04)
Movie browsing API (TMDB integration) and the booking/payment API (Stripe integration).

### Phase 3: User Management (Plans 05–06)
Authentication and account management APIs.

### Phase 4: Supporting Features & Testing (Plans 07–08)
Calendar events, food menu, gift cards, contact/rental forms, and comprehensive test coverage.

---

## Relationship to Frontend Plans

The frontend calls the Laravel API directly (no Nuxt BFF layer). The backend v1 is complete with 410 tests. The frontend plans are ready for execution — composables will call the Laravel API endpoints defined here.

---

## Won't Have (This Phase)

- Admin panel / dashboard
- Email sending (stubs only — mail driver: log)
- Stripe webhooks (synchronous confirmation first)
- WebSocket seat holds
- Rate limiting rules (fail2ban handles IP-level; API-level deferred)
- Search functionality (filter endpoints are sufficient for MVP)
- Image upload/storage (TMDB provides all media)
