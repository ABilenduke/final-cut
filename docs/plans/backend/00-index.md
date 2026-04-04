# Backend Implementation Plans — Master Index

> **Project:** Final Cut Movie Theatre
> **Stack:** Laravel 13, PHP 8.3, PostgreSQL, Redis, Pest
> **Methodology:** MoSCoW prioritization, dependency-first ordering, domain grouping, inline testing

---

## Plan Summary

| # | Plan | MoSCoW | Complexity | Depends On | Status |
|---|------|--------|------------|------------|--------|
| 01 | [Project Setup & Config](01-project-setup-and-config.md) | Must Have | S | None | Pending |
| 02 | [Database Schema](02-database-schema.md) | Must Have | L | 01 | Pending |
| 03 | [Movie API](03-movie-api.md) | Must Have | M | 02 | Pending |
| 04 | [Booking API](04-booking-api.md) | Must Have | XL | 02, 03 | Pending |
| 05 | [Auth API](05-auth-api.md) | Must Have | M | 02 | Pending |
| 06 | [Account API](06-account-api.md) | Should Have | M | 02, 05 | Pending |
| 07 | [Calendar & Content API](07-calendar-content-api.md) | Should Have | M | 02 | Pending |
| 08 | [Testing & Seeding](08-testing-and-seeding.md) | Should Have | M | 03–07 | Pending |

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

The backend serves the frontend's Nuxt server routes (BFF layer). During initial development, the frontend uses mock JSON data. The backend is needed when:

1. **Real TMDB data** replaces mock movies (Frontend Plan 05, Task 2)
2. **Real seat persistence** replaces mock availability (Frontend Plan 08)
3. **Real Stripe payments** replace mock transactions (Frontend Plan 08)
4. **Real user accounts** replace mock auth (Frontend Plan 09)

The two stacks can be developed in parallel: frontend with mock data, backend with Pest tests. They converge at integration time.

---

## Won't Have (This Phase)

- Admin panel / dashboard
- Email sending (stubs only — mail driver: log)
- Stripe webhooks (synchronous confirmation first)
- WebSocket seat holds
- Rate limiting rules (fail2ban handles IP-level; API-level deferred)
- Search functionality (filter endpoints are sufficient for MVP)
- Image upload/storage (TMDB provides all media)
