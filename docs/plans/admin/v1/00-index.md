# Admin v1 — Planning Index

> **Status:** Planning only — no implementation in v1
> **Timeline:** v2, after customer-facing frontend ships
> **Approach:** Separate admin app (not integrated into customer frontend)

## Why Deferred

The v1 backend has no role/permission model, no admin endpoints, and no admin UI contract. Building admin screens now would require either fake/stubbed frontend work or premature backend redesign. Customer and admin concerns are operationally different — a separate surface is cleaner for security, permissions, deployment, and maintenance.

## V1 Catalog Management

Until admin is built, catalog management happens via:
- `php artisan tinker` for ad-hoc changes
- Database seeders for bulk data (`make fresh`)
- `movies:enrich` artisan command for TMDB metadata backfill

## Required Backend Capabilities (Build First)

Before any admin UI can be implemented, the backend needs:

1. **Role model** — Add `role` column to users table (enum: `user` / `admin`)
2. **Admin middleware** — Route middleware that checks `user.role === 'admin'`
3. **Admin API routes** — CRUD endpoints for:
   - Movies (create, update, delete, trigger enrichment)
   - Locations (create, update)
   - Auditoriums and seats (create, update per location)
   - Showtimes (create, update, cancel per location)
   - Menu items (create, update, toggle availability per location)
   - Calendar events (create, update, delete)
   - Promo codes (create, update, deactivate)
   - Gift cards (view, void)
   - Bookings (view, cancel, refund)
   - Users (view, update tier, adjust points)

## Admin App Architecture

- **Separate SPA** on a distinct subdomain (e.g., `admin.finalcut.test`)
- **Or Laravel Filament** — admin panel framework built on Laravel, generates UI from Eloquent models
- Stricter CSP and network controls than the customer app
- Potentially gated by VPN or IP allowlist in production

## Plans (To Be Written)

| # | Plan | Description |
|---|------|-------------|
| 01 | Backend roles & middleware | User role enum, admin middleware, admin guard |
| 02 | Admin API routes | CRUD controllers and resources for all entities |
| 03 | Admin app setup | Separate app scaffold, auth integration |
| 04 | Core admin pages | Movies, showtimes, locations, auditoriums |
| 05 | Operations pages | Bookings, gift cards, promo codes, users |
| 06 | Content admin | Events, menu items, calendar management |
