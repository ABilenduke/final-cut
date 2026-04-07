/**
 * Centralized API client for Laravel backend.
 *
 * Two functions for two use cases:
 * - `apiFetch<T>(path, opts)` — imperative, returns Promise<T>, throws ApiErrorResponse.
 *   Use for: mutations (POST/PATCH/DELETE), client-only fetches, auth calls.
 * - `useApiFetch<T>(path, opts)` — wraps Nuxt's useFetch with base config.
 *   Use for: SSR-compatible GET requests in page setup.
 *
 * All composables MUST use these functions. Never configure credentials,
 * headers, or error handling independently.
 */

export interface ApiError {
  message: string
  field?: string
}

export interface ApiErrorResponse {
  errors: ApiError[]
  status: number
}

// Module-level CSRF state
let csrfBootstrapped = false

/** Reset CSRF state — call on auth transitions (login/logout) and exposed for testing */
export function _resetCsrf() {
  csrfBootstrapped = false
}

async function ensureCsrf(baseURL: string): Promise<void> {
  if (csrfBootstrapped) return
  await $fetch('/sanctum/csrf-cookie', { baseURL, credentials: 'include' })
  csrfBootstrapped = true
}

async function refreshCsrf(baseURL: string): Promise<void> {
  csrfBootstrapped = false
  await ensureCsrf(baseURL)
}

function getXsrfToken(): string | null {
  if (import.meta.server) return null
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match?.[1] ? decodeURIComponent(match[1]) : null
}

function parseApiError(error: unknown): ApiErrorResponse {
  if (error && typeof error === 'object' && 'data' in error) {
    const data = (error as any).data
    const status = (error as any).status ?? (error as any).statusCode ?? 500

    if (data?.errors && Array.isArray(data.errors)) {
      return { errors: data.errors, status }
    }
    if (data?.message) {
      return { errors: [{ message: data.message }], status }
    }
    return { errors: [{ message: 'An unexpected error occurred' }], status }
  }
  return { errors: [{ message: 'Network error. Please check your connection.' }], status: 0 }
}

/**
 * Imperative API client for mutations and client-only fetches.
 * Returns Promise<T>, throws ApiErrorResponse on failure.
 */
export async function apiFetch<T>(
  path: string,
  options: {
    method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
    body?: Record<string, any> | null
    query?: Record<string, unknown>
    idempotencyKey?: string
  } = {},
): Promise<T> {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBaseUrl as string
  const method = options.method ?? 'GET'

  // CSRF bootstrap for state-changing requests
  if (method !== 'GET') {
    await ensureCsrf(baseURL)
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
  }

  // Read XSRF token from cookie (set by Sanctum csrf-cookie endpoint)
  const xsrfToken = getXsrfToken()
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = xsrfToken
  }

  if (options.idempotencyKey) {
    headers['Idempotency-Key'] = options.idempotencyKey
  }

  try {
    return await $fetch<T>(path, {
      baseURL,
      method,
      body: options.body,
      query: options.query,
      credentials: 'include',
      headers,
    })
  } catch (error: unknown) {
    const parsed = parseApiError(error)

    // On 419 (CSRF token mismatch), refresh CSRF and retry once
    if (parsed.status === 419 && method !== 'GET') {
      await refreshCsrf(baseURL)
      const freshToken = getXsrfToken()
      if (freshToken) {
        headers['X-XSRF-TOKEN'] = freshToken
      }
      try {
        return await $fetch<T>(path, {
          baseURL,
          method,
          body: options.body,
          query: options.query,
          credentials: 'include',
          headers,
        })
      } catch (retryError: unknown) {
        throw parseApiError(retryError)
      }
    }

    throw parsed
  }
}

/**
 * SSR-compatible wrapper around Nuxt's useFetch.
 * Use for GET requests in page setup that benefit from SSR dedup/hydration.
 */
export function useApiFetch<T>(
  path: string | Ref<string>,
  options: {
    query?: Record<string, unknown>
    watch?: any[]
    immediate?: boolean
  } = {},
) {
  const config = useRuntimeConfig()
  return useFetch<T>(path, {
    baseURL: config.public.apiBaseUrl as string,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    ...options,
  })
}
