import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock $fetch before importing api module
const mockFetch = vi.fn()
vi.stubGlobal('$fetch', mockFetch)

// Note: useRuntimeConfig is provided by Nuxt test env.
// baseURL in tests will be '' (empty) since NUXT_PUBLIC_API_BASE_URL isn't set.
// Tests validate behavior (CSRF, headers, errors), not config values.

import { apiFetch, _resetCsrf } from '~/utils/api'
import type { ApiErrorResponse } from '~/utils/api'

describe('apiFetch', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockFetch.mockResolvedValue({ data: [] })
    // Clear any XSRF cookie
    Object.defineProperty(document, 'cookie', { value: '', writable: true, configurable: true })
    _resetCsrf()
  })

  it('sends GET requests with credentials and Accept header', async () => {
    await apiFetch('/api/movies')
    expect(mockFetch).toHaveBeenCalledTimes(1)
    const [path, opts] = mockFetch.mock.calls[0]
    expect(path).toBe('/api/movies')
    expect(opts.method).toBe('GET')
    expect(opts.credentials).toBe('include')
    expect(opts.headers.Accept).toBe('application/json')
    expect(opts.baseURL).toBeDefined()
  })

  it('does not fetch CSRF cookie before GET requests', async () => {
    await apiFetch('/api/movies')
    expect(mockFetch).toHaveBeenCalledTimes(1)
    expect(mockFetch.mock.calls[0][0]).toBe('/api/movies')
  })

  it('fetches CSRF cookie before first POST request', async () => {
    mockFetch.mockResolvedValue({ data: {} })
    await apiFetch('/api/auth/login', { method: 'POST', body: { email: 'a@b.c' } })
    expect(mockFetch).toHaveBeenCalledTimes(2)
    const [csrfPath, csrfOpts] = mockFetch.mock.calls[0]
    expect(csrfPath).toBe('/sanctum/csrf-cookie')
    expect(csrfOpts.credentials).toBe('include')
    expect(csrfOpts.baseURL).toBeDefined()
  })

  it('fetches CSRF cookie only once across multiple mutations', async () => {
    mockFetch.mockImplementation((path: string) => {
      if (path === '/sanctum/csrf-cookie') {
        // Simulate Sanctum setting the XSRF-TOKEN cookie
        Object.defineProperty(document, 'cookie', {
          value: 'XSRF-TOKEN=csrf-token-abc',
          writable: true,
          configurable: true,
        })
        return Promise.resolve(undefined)
      }
      return Promise.resolve({ data: {} })
    })
    await apiFetch('/api/auth/login', { method: 'POST', body: {} })
    await apiFetch('/api/auth/logout', { method: 'POST' })
    // 1 csrf + 2 actual = 3 total
    expect(mockFetch).toHaveBeenCalledTimes(3)
    // Only the first call should be csrf-cookie
    expect(mockFetch.mock.calls[0][0]).toBe('/sanctum/csrf-cookie')
    expect(mockFetch.mock.calls[1][0]).toBe('/api/auth/login')
    expect(mockFetch.mock.calls[2][0]).toBe('/api/auth/logout')
  })

  it('re-bootstraps CSRF when cookie is cleared after initial bootstrap', async () => {
    // First call: bootstrap sets cookie
    mockFetch.mockImplementation((path: string) => {
      if (path === '/sanctum/csrf-cookie') {
        Object.defineProperty(document, 'cookie', {
          value: 'XSRF-TOKEN=csrf-token-abc',
          writable: true,
          configurable: true,
        })
        return Promise.resolve(undefined)
      }
      return Promise.resolve({ data: {} })
    })
    await apiFetch('/api/auth/login', { method: 'POST', body: {} })
    expect(mockFetch).toHaveBeenCalledTimes(2) // csrf + login

    // Simulate cookie expiry/clear
    Object.defineProperty(document, 'cookie', { value: '', writable: true, configurable: true })
    await apiFetch('/api/auth/logout', { method: 'POST' })
    // Should re-bootstrap: csrf + logout = 2 more calls
    expect(mockFetch).toHaveBeenCalledTimes(4)
    expect(mockFetch.mock.calls[2][0]).toBe('/sanctum/csrf-cookie')
    expect(mockFetch.mock.calls[3][0]).toBe('/api/auth/logout')
  })

  it('deduplicates concurrent CSRF bootstrap requests', async () => {
    let csrfCallCount = 0
    mockFetch.mockImplementation((path: string) => {
      if (path === '/sanctum/csrf-cookie') {
        csrfCallCount++
        Object.defineProperty(document, 'cookie', {
          value: 'XSRF-TOKEN=csrf-token-abc',
          writable: true,
          configurable: true,
        })
        return Promise.resolve(undefined)
      }
      return Promise.resolve({ data: {} })
    })
    // Fire two mutations concurrently before CSRF bootstrap completes
    await Promise.all([
      apiFetch('/api/auth/login', { method: 'POST', body: {} }),
      apiFetch('/api/auth/logout', { method: 'POST' }),
    ])
    // Only one CSRF bootstrap call despite two concurrent mutations
    expect(csrfCallCount).toBe(1)
    expect(mockFetch).toHaveBeenCalledTimes(3) // 1 csrf + 2 actual
  })

  it('sends X-XSRF-TOKEN header when cookie exists', async () => {
    Object.defineProperty(document, 'cookie', {
      value: 'XSRF-TOKEN=test-token-123',
      writable: true,
      configurable: true,
    })
    await apiFetch('/api/movies')
    const [, opts] = mockFetch.mock.calls[0]
    expect(opts.headers['X-XSRF-TOKEN']).toBe('test-token-123')
  })

  it('does not send X-XSRF-TOKEN header when no cookie', async () => {
    await apiFetch('/api/movies')
    const [, opts] = mockFetch.mock.calls[0]
    expect(opts.headers['X-XSRF-TOKEN']).toBeUndefined()
  })

  it('sends Idempotency-Key header when provided', async () => {
    mockFetch.mockResolvedValue({ data: {} })
    await apiFetch('/api/gift-cards/purchase', {
      method: 'POST',
      body: {},
      idempotencyKey: 'uuid-123',
    })
    // Second call (after CSRF) should have the header
    const [, opts] = mockFetch.mock.calls[1]
    expect(opts.headers['Idempotency-Key']).toBe('uuid-123')
  })

  it('parses error envelope with errors array', async () => {
    mockFetch.mockRejectedValue({
      data: { errors: [{ message: 'Not found', field: 'slug' }] },
      status: 404,
    })
    try {
      await apiFetch('/api/movies/bad-slug')
      expect.unreachable('Should have thrown')
    } catch (e) {
      const err = e as ApiErrorResponse
      expect(err.status).toBe(404)
      expect(err.errors).toEqual([{ message: 'Not found', field: 'slug' }])
    }
  })

  it('parses error with single message format', async () => {
    mockFetch.mockRejectedValue({
      data: { message: 'Unauthenticated.' },
      status: 401,
    })
    try {
      await apiFetch('/api/auth/me')
      expect.unreachable('Should have thrown')
    } catch (e) {
      const err = e as ApiErrorResponse
      expect(err.status).toBe(401)
      expect(err.errors[0].message).toBe('Unauthenticated.')
    }
  })

  it('handles network errors gracefully', async () => {
    mockFetch.mockRejectedValue(new TypeError('fetch failed'))
    try {
      await apiFetch('/api/movies')
      expect.unreachable('Should have thrown')
    } catch (e) {
      const err = e as ApiErrorResponse
      expect(err.status).toBe(0)
      expect(err.errors[0].message).toContain('Network error')
    }
  })

  it('passes query parameters', async () => {
    await apiFetch('/api/movies', { query: { status: 'now_showing' } })
    const [, opts] = mockFetch.mock.calls[0]
    expect(opts.query).toEqual({ status: 'now_showing' })
  })

  it('passes request body on POST', async () => {
    mockFetch.mockResolvedValue({ data: {} })
    await apiFetch('/api/auth/login', {
      method: 'POST',
      body: { email: 'test@test.com', password: 'secret' },
    })
    const [, opts] = mockFetch.mock.calls[1]
    expect(opts.body).toEqual({ email: 'test@test.com', password: 'secret' })
  })

  it('fetches CSRF before PATCH requests', async () => {
    mockFetch.mockResolvedValue({ data: {} })
    _resetCsrf()
    await apiFetch('/api/account/profile', { method: 'PATCH', body: { name: 'New' } })
    expect(mockFetch.mock.calls[0][0]).toBe('/sanctum/csrf-cookie')
  })

  it('fetches CSRF before DELETE requests', async () => {
    mockFetch.mockResolvedValue({ data: { success: true } })
    _resetCsrf()
    await apiFetch('/api/account/payment-methods/pm-1', { method: 'DELETE' })
    expect(mockFetch.mock.calls[0][0]).toBe('/sanctum/csrf-cookie')
  })

  it('retries once on 419 CSRF mismatch for mutations', async () => {
    mockFetch
      .mockResolvedValueOnce(undefined) // CSRF bootstrap
      .mockRejectedValueOnce({ data: { message: 'CSRF token mismatch.' }, status: 419 })
      .mockResolvedValueOnce(undefined) // CSRF refresh
      .mockResolvedValueOnce({ data: { success: true } }) // retry
    _resetCsrf()
    const result = await apiFetch('/api/auth/logout', { method: 'POST' })
    expect(result).toEqual({ data: { success: true } })
    // 1 csrf + 1 fail + 1 csrf refresh + 1 retry = 4 calls
    expect(mockFetch).toHaveBeenCalledTimes(4)
  })

  it('throws on 419 retry failure', async () => {
    mockFetch
      .mockResolvedValueOnce(undefined) // CSRF bootstrap
      .mockRejectedValueOnce({ data: { message: 'CSRF token mismatch.' }, status: 419 })
      .mockResolvedValueOnce(undefined) // CSRF refresh
      .mockRejectedValueOnce({ data: { message: 'CSRF token mismatch.' }, status: 419 }) // retry also fails
    _resetCsrf()
    try {
      await apiFetch('/api/auth/logout', { method: 'POST' })
      expect.unreachable('Should have thrown')
    } catch (e) {
      const err = e as ApiErrorResponse
      expect(err.status).toBe(419)
    }
  })

  it('does not retry 419 on GET requests', async () => {
    mockFetch.mockRejectedValue({ data: { message: 'CSRF token mismatch.' }, status: 419 })
    try {
      await apiFetch('/api/movies')
      expect.unreachable('Should have thrown')
    } catch (e) {
      const err = e as ApiErrorResponse
      expect(err.status).toBe(419)
    }
    // Only 1 call — no retry for GET
    expect(mockFetch).toHaveBeenCalledTimes(1)
  })
})
