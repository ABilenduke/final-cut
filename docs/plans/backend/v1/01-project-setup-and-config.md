# Plan 01: Project Setup & Configuration

> **Priority:** Must Have
> **Complexity:** S
> **Depends On:** None — foundational
> **Unlocks:** Plans 02, 03, 04, 05, 06, 07 (everything)

## Overview

Configure the Laravel project for API development: CORS, service configuration for TMDB and Stripe, API route scaffolding, middleware registration, and a standardized response format. This plan transforms the bare Laravel scaffold into a ready-to-develop API backend.

## Reference Documents

- `docs/DATA_MODELS.md` — API route inventory (Section 2)
- `docs/SITE_ARCHITECTURE.md` — Environment variables, frontend-backend architecture

---

## Tasks

### Task 1: API Routes File with Stubs

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/routes/api.php` — Create with all route definitions
- **Details:**
  Define all API routes per DATA_MODELS.md Section 2, returning empty 200 responses initially. Organize by resource group:

  ```php
  // Movies
  Route::get('/movies', [MovieController::class, 'index']);
  Route::get('/movies/{slug}', [MovieController::class, 'show']);
  Route::get('/movies/{slug}/showtimes', [MovieController::class, 'showtimes']);

  // Showtimes
  Route::get('/showtimes/{id}', [ShowtimeController::class, 'show']);

  // Bookings
  Route::post('/bookings', [BookingController::class, 'store']);
  Route::get('/bookings/{id}', [BookingController::class, 'show']);
  Route::post('/bookings/confirm', [BookingController::class, 'confirm']);

  // Calendar
  Route::get('/calendar/events', [CalendarEventController::class, 'index']);
  Route::get('/calendar/events/{slug}', [CalendarEventController::class, 'show']);

  // Food Menu
  Route::get('/food-menu', [FoodMenuController::class, 'index']);

  // Auth
  Route::post('/auth/register', [AuthController::class, 'register']);
  Route::post('/auth/login', [AuthController::class, 'login']);
  Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
  Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
  Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

  // Account (all auth-protected)
  Route::middleware('auth:sanctum')->prefix('account')->group(function () {
    Route::get('/profile', [AccountController::class, 'profile']);
    Route::patch('/profile', [AccountController::class, 'updateProfile']);
    Route::get('/orders', [AccountController::class, 'orders']);
    Route::get('/bookings', [AccountController::class, 'bookings']);
    Route::get('/loyalty', [AccountController::class, 'loyalty']);
    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
    Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);
  });

  // Gift Cards
  Route::post('/gift-cards/purchase', [GiftCardController::class, 'purchase']);
  Route::get('/gift-cards/balance', [GiftCardController::class, 'balance']);

  // Contact / Rentals
  Route::post('/rentals/inquiry', [RentalController::class, 'store']);
  Route::post('/contact', [ContactController::class, 'store']);
  ```

  Create stub controllers for each with empty methods returning `response()->json(['status' => 'ok'])`.

- **Acceptance Criteria:**
  - [ ] All 26 routes defined and reachable
  - [ ] Stub controllers created in `app/Http/Controllers/Api/`
  - [ ] `php artisan route:list` shows all routes
  - [ ] Each stub returns 200 JSON response

---

### Task 2: Service Configuration

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `backend/config/services.php` — Add TMDB and Stripe config
  - `backend/.env` — Add environment variables
  - `backend/.env.example` — Document all env vars
- **Details:**
  ```php
  // config/services.php
  'tmdb' => [
    'api_key' => env('TMDB_API_KEY'),
    'base_url' => 'https://api.themoviedb.org/3',
    'image_base_url' => 'https://image.tmdb.org/t/p/',
  ],
  'stripe' => [
    'secret' => env('STRIPE_SECRET_KEY'),
    'publishable' => env('STRIPE_PUBLISHABLE_KEY'),
  ],
  ```

- **Acceptance Criteria:**
  - [ ] TMDB config accessible via `config('services.tmdb')`
  - [ ] Stripe config accessible via `config('services.stripe')`
  - [ ] `.env.example` documents all required variables

---

### Task 3: CORS Configuration

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `backend/config/cors.php` — Configure for frontend origin
- **Details:**
  Allow requests from the frontend origin (`https://finalcut.test`):
  ```php
  'allowed_origins' => [env('FRONTEND_URL', 'https://finalcut.test')],
  'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
  'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
  'supports_credentials' => true, // For session cookies
  ```

- **Acceptance Criteria:**
  - [ ] Frontend can make cross-origin requests to backend
  - [ ] Credentials (cookies) supported for session auth
  - [ ] OPTIONS preflight returns correct headers

---

### Task 4: Middleware Configuration

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `backend/bootstrap/app.php` ��� Register middleware
- **Details:**
  Register API middleware stack:
  - Throttle middleware for rate limiting (60 requests/minute default)
  - CORS middleware
  - Sanctum (or session) auth middleware for protected routes

  Install Laravel Sanctum for API token/session authentication:
  ```bash
  composer require laravel/sanctum
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
  ```

- **Acceptance Criteria:**
  - [ ] Sanctum installed and configured
  - [ ] Rate limiting applied to API routes
  - [ ] Auth middleware protects account routes
  - [ ] Unauthenticated requests to protected routes return 401

---

### Task 5: Base API Controller & Response Format

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `backend/app/Http/Controllers/Api/Controller.php` — Base controller
- **Details:**
  Standardized JSON response format:
  ```php
  // Success: { "data": {...}, "meta": {...} }
  // Error: { "errors": [{ "field": "...", "message": "..." }] }
  // List: { "data": [...], "meta": { "total": N, "page": N, "per_page": N } }
  ```

  Helper methods on base controller:
  - `successResponse($data, $meta = null, $status = 200)`
  - `errorResponse($errors, $status = 400)`
  - `paginatedResponse($paginator)`

- **Acceptance Criteria:**
  - [ ] Base controller with response helper methods
  - [ ] All stub controllers extend base controller
  - [ ] Consistent JSON envelope on all responses

---

## Testing Requirements

- **Pest Test:** Verify all 26 route stubs return 200
- **CORS Test:** Verify cross-origin request from frontend domain succeeds
- **Auth Test:** Verify protected routes return 401 without auth token

## Dependencies Map

```
Task 2 (Services Config) ← independent
Task 3 (CORS) ← independent
Task 4 (Middleware + Sanctum) ← independent
Task 5 (Base Controller) ← independent
Task 1 (Routes + Stubs) ← needs Tasks 4, 5 (controllers extend base, use middleware)
```

## Risks & Open Questions

1. **Sanctum vs session auth** — The frontend uses `nuxt-auth-utils` with encrypted cookies. Need to decide if the backend uses Sanctum tokens (stateless) or session cookies (stateful). Recommendation: Sanctum with cookie-based SPA authentication (stateful, same-site).
2. **API versioning** — No versioning in the current route structure (`/api/movies` not `/api/v1/movies`). Acceptable for MVP; add versioning if the API becomes public.
3. **PostgreSQL connection** — Verify the Docker PostgreSQL container is accessible from the backend container. Connection uses TLS per the project's security setup.
