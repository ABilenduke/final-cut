# Plan 05: Authentication API

> **Priority:** Must Have
> **Complexity:** M
> **Depends On:** Plan 02 (User model with profile fields)
> **Unlocks:** Plan 06 (Account API needs auth)

## Overview

Implement session-based authentication endpoints: register, login, logout, current user retrieval, and password reset (stub). Uses Laravel Sanctum for SPA cookie-based authentication.

## Reference Documents

- `docs/DATA_MODELS.md` — Section 2 (Auth routes)
- `docs/SITE_ARCHITECTURE.md` — Session-based auth, nuxt-auth-utils
- `docs/STATE_MANAGEMENT.md` — useAuth composable interface

---

## Tasks

### Task 1: AuthController

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `backend/app/Http/Controllers/Api/AuthController.php`
  - `backend/app/Http/Requests/LoginRequest.php`
  - `backend/app/Http/Requests/RegisterRequest.php`
  - `backend/app/Http/Resources/UserResource.php`
- **Details:**
  **`register` — POST `/api/auth/register`:**
  - Validate: name (required, string), email (required, email, unique:users), password (required, min:8, confirmed)
  - Create user with hashed password
  - Issue Sanctum token or create session
  - Return: `{ data: User }` + auth cookie/token

  **`login` — POST `/api/auth/login`:**
  - Validate: email (required), password (required)
  - Attempt authentication
  - On failure: 401 with `{ errors: [{ message: 'Invalid credentials' }] }`
  - On success: Return `{ data: User }` + auth cookie/token

  **`logout` — POST `/api/auth/logout`:** (auth required)
  - Revoke current token / destroy session
  - Return: `{ data: { success: true } }`

  **`me` — GET `/api/auth/me`:** (auth required)
  - Return current user from auth
  - Return: `{ data: User }`

  **`forgotPassword` — POST `/api/auth/forgot-password`:**
  - Validate: email (required, email)
  - Stub: log the email, return success regardless of whether email exists (prevent email enumeration)
  - Return: `{ data: { success: true } }`

- **Acceptance Criteria:**
  - [ ] Registration creates user and returns auth credential
  - [ ] Login with correct credentials returns user
  - [ ] Login with wrong credentials returns 401
  - [ ] Logout clears auth state
  - [ ] `me` returns current authenticated user
  - [ ] Forgot password doesn't reveal whether email exists
  - [ ] Password stored with bcrypt hash

---

### Task 2: Sanctum SPA Configuration

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `backend/config/sanctum.php` — Update stateful domains
  - `backend/config/session.php` — Cookie settings
  - `backend/config/cors.php` — Ensure credentials supported
- **Details:**
  Configure Sanctum for SPA cookie-based auth (stateful):
  ```php
  // config/sanctum.php
  'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'finalcut.test')),
  ```

  Session cookie config:
  ```php
  // config/session.php
  'driver' => 'database', // or 'redis'
  'domain' => env('SESSION_DOMAIN', '.finalcut.test'),
  'same_site' => 'lax',
  'secure' => true,
  'http_only' => true,
  ```

  CORS must allow credentials for cookie transmission.

- **Acceptance Criteria:**
  - [ ] Sanctum stateful domains include `finalcut.test`
  - [ ] Session cookies set with correct domain, secure, httponly
  - [ ] CSRF protection works for SPA authentication
  - [ ] Authentication persists across requests via cookie

---

### Task 3: Form Request Validation

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `backend/app/Http/Requests/LoginRequest.php`
  - `backend/app/Http/Requests/RegisterRequest.php`
- **Details:**
  **RegisterRequest rules:**
  - `name`: required, string, max:255
  - `email`: required, email, unique:users, max:255
  - `password`: required, string, min:8, confirmed

  **LoginRequest rules:**
  - `email`: required, email
  - `password`: required, string

  Both return JSON validation errors in the standard error format.

- **Acceptance Criteria:**
  - [ ] Registration rejects duplicate emails with clear message
  - [ ] Password must be at least 8 characters
  - [ ] Validation errors return in standard error format
  - [ ] Missing fields return 422 with field-level errors

---

## Testing Requirements

- **Pest Feature Tests:**
  - Register: success, duplicate email (422), weak password (422), missing fields (422)
  - Login: success, wrong password (401), nonexistent email (401)
  - Logout: clears session, subsequent `me` returns 401
  - Me: returns user when authenticated, 401 when not
  - Forgot password: always returns success (no email enumeration)
  - Full flow: register → me → logout → me (should fail) → login → me (should work)
- **Security Tests:**
  - Password not returned in any response
  - Rate limiting on login endpoint (prevent brute force)
  - Session fixation protection (new session on login)

## Dependencies Map

```
Task 2 (Sanctum Config) ← foundational
Task 3 (Form Requests) ← independent
Task 1 (AuthController) ← uses Tasks 2, 3
```

## Risks & Open Questions

1. **Sanctum SPA vs token auth** — The frontend uses `nuxt-auth-utils` which may expect a different auth flow than Sanctum SPA. Verify compatibility. If `nuxt-auth-utils` manages its own sessions (server-side), the backend may just need a simple credential verification endpoint without session management.
2. **CSRF tokens** — Sanctum SPA auth requires CSRF tokens. The frontend needs to fetch `/sanctum/csrf-cookie` before making auth requests. Document this in the integration guide.
3. **Redis sessions** — Production should use Redis for session storage (already configured in Docker). Development can use database sessions.
