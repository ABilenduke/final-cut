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

function shouldUseRelativeApiPath(path: string): boolean {
  if (!import.meta.server || !path.startsWith('/')) {
    return false
  }
  // When NUXT_PUBLIC_API_BASE_URL is explicitly configured, honor it
  // server-side so SSR/ISR renders can reach the backend via the
  // configured origin (e.g., through nginx) instead of looping back
  // to the Nuxt server itself.
  const configured = String(useRuntimeConfig().public.apiBaseUrl ?? '').trim()
  return configured === ''
}

function resolveApiBaseUrl(): string {
  const config = useRuntimeConfig()
  const configuredBaseUrl = String(config.public.apiBaseUrl ?? '').trim()

  if (configuredBaseUrl) {
    return configuredBaseUrl
  }

  // Same-origin fallback keeps local dev working when requests are meant to
  // flow through the public domain/reverse proxy and no explicit API base URL
  // has been configured yet.
  if (import.meta.server) {
    return useRequestURL().origin
  }

  if (import.meta.client) {
    return window.location.origin
  }

  return ''
}

// Module-level CSRF state
let csrfBootstrapped = false
let csrfPromise: Promise<void> | null = null

/** Reset CSRF state — call on auth transitions (login/logout) and exposed for testing */
export function _resetCsrf() {
  csrfBootstrapped = false
  csrfPromise = null
}

async function ensureCsrf(baseURL: string): Promise<void> {
  if (csrfBootstrapped && getXsrfToken()) return

  if (!csrfPromise) {
    csrfPromise = (async () => {
      await $fetch('/api/sanctum/csrf-cookie', { baseURL, credentials: 'include' })
      csrfBootstrapped = true
    })().finally(() => {
      csrfPromise = null
    })
  }

  await csrfPromise
}

async function refreshCsrf(baseURL: string): Promise<void> {
  csrfBootstrapped = false
  csrfPromise = null
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
  const baseURL = shouldUseRelativeApiPath(path) ? '' : resolveApiBaseUrl()
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
    /** Optional explicit cache key. When set, Nuxt uses this instead of the
     *  auto-derived key. Required when the same URL is fetched with different
     *  query params that must produce independently-cached ISR entries. */
    key?: string
  } = {},
) {
  const resolvedPath = typeof path === 'string' ? path : path.value
  const baseURL = shouldUseRelativeApiPath(resolvedPath) ? undefined : resolveApiBaseUrl()

  return useFetch<T>(path, {
    ...(baseURL ? { baseURL } : {}),
    credentials: 'include',
    headers: { Accept: 'application/json' },
    ...options,
  })
}
