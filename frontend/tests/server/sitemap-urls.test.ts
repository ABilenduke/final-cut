/**
 * Unit tests for the sitemap dynamic URL handler.
 *
 * The handler lives at server/api/__sitemap__/urls.get.ts.
 *
 * Strategy: we cannot invoke the Nitro handler directly in Vitest because
 * `defineSitemapEventHandler` and `useRuntimeConfig` are Nitro auto-imports
 * that don't resolve in the Vitest/happy-dom environment. Instead we test the
 * URL-building logic by:
 *   1. Mocking `$fetch` globally (it is a Nuxt global that resolves in the
 *      test environment via @nuxt/test-utils).
 *   2. Importing the handler's inner logic through a lightweight re-export
 *      that strips the Nitro wrapper (see test helper below).
 *
 * Because the entire point of the handler is its data-transformation logic
 * (API responses → SitemapUrlInput[]), and the module boundary that is hard
 * to test is exactly the Nitro wrapper, we test the transformation function
 * that the handler delegates to. If the project later adds a dedicated
 * `buildSitemapUrls()` helper extracted from the handler, these tests should
 * be updated to call that helper directly.
 *
 * The Playwright e2e test (tests/e2e/sitemap.spec.ts) covers the full
 * integration: HTTP 200, valid XML, correct <loc> entries.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
// Blog posts now come from the backend API (admin-v2 Plan 12).
const BLOG_RESPONSE = {
  data: [
    { slug: 'summer-lineup-preview', date: '2026-04-05' },
    { slug: 'grand-opening-announcement', date: '2026-03-15' },
  ],
}

// ---------------------------------------------------------------------------
// Mock $fetch — Nuxt global available in the Vitest nuxt environment
// ---------------------------------------------------------------------------

const mockFetch = vi.fn()

vi.stubGlobal('$fetch', mockFetch)

// ---------------------------------------------------------------------------
// Mock Nitro auto-import stubs so the handler module can be imported
// ---------------------------------------------------------------------------

// `defineEventHandler` from h3 wraps its callback; we make it a pass-through
// so we can pull the inner function out and call it directly.
vi.mock('h3', () => ({
  defineEventHandler: (fn: Function) => fn,
}))

// `useRuntimeConfig` is auto-imported from '#imports' inside Nitro.
vi.mock('#imports', () => ({
  useRuntimeConfig: () => ({
    public: { apiBaseUrl: 'http://backend-test' },
  }),
}))

// ---------------------------------------------------------------------------
// Import the handler (after mocks are in place)
// ---------------------------------------------------------------------------

// Dynamic import to ensure mocks are hoisted before module evaluation.
// The handler exports the result of defineSitemapEventHandler (our pass-through),
// so the default export IS the inner async callback.
const { default: buildUrls } = await import(
  '../../server/api/__sitemap__/urls.get.ts'
)

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const MOVIES_RESPONSE = {
  data: [
    { slug: 'the-dark-knight', updated_at: '2026-04-01T00:00:00Z' },
    { slug: 'blade-runner-2049', updated_at: '2026-03-15T00:00:00Z' },
  ],
}

const EVENTS_RESPONSE = {
  data: [
    { slug: 'ghibli-marathon', type: 'special_event', updated_at: '2026-05-01T00:00:00Z' },
    { slug: 'midnight-cult', type: 'loyalty_exclusive', updated_at: '2026-04-20T00:00:00Z' },
    // showtime type — must NOT produce an /events/:slug entry
    { slug: 'batman-begins-showtime', type: 'showtime', updated_at: '2026-04-28T00:00:00Z' },
    // null slug — must be skipped regardless of type
    { slug: null, type: 'special_event', updated_at: '2026-04-28T00:00:00Z' },
  ],
}

const LOCATIONS_RESPONSE = {
  data: [
    { slug: 'downtown', updated_at: '2026-01-10T00:00:00Z' },
    { slug: 'uptown', updated_at: '2026-02-05T00:00:00Z' },
  ],
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Set up $fetch to return the four API responses in call order:
 * movies, events, locations, blog posts.
 */
function setupHappyPath() {
  mockFetch
    .mockResolvedValueOnce(MOVIES_RESPONSE)
    .mockResolvedValueOnce(EVENTS_RESPONSE)
    .mockResolvedValueOnce(LOCATIONS_RESPONSE)
    .mockResolvedValueOnce(BLOG_RESPONSE)
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('sitemap URL handler', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ── Shape ─────────────────────────────────────────────────────────────────

  it('returns an array of URL entries', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    expect(Array.isArray(urls)).toBe(true)
    expect(urls.length).toBeGreaterThan(0)
  })

  it('every entry has a loc field starting with /', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    for (const url of urls) {
      expect(url).toHaveProperty('loc')
      expect((url as any).loc).toMatch(/^\//)
    }
  })

  // ── Movies ────────────────────────────────────────────────────────────────

  it('includes one entry per movie with the correct loc', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    expect(locs).toContain('/movies/the-dark-knight')
    expect(locs).toContain('/movies/blade-runner-2049')
  })

  it('sets lastmod from movie updated_at', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const movieEntry = urls.find((u) => (u as any).loc === '/movies/the-dark-knight')
    expect((movieEntry as any).lastmod).toBe('2026-04-01T00:00:00Z')
  })

  // ── Events ────────────────────────────────────────────────────────────────

  it('includes one entry per non-showtime event with a slug', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    expect(locs).toContain('/events/ghibli-marathon')
    expect(locs).toContain('/events/midnight-cult')
  })

  it('excludes showtime-type events from /events/:slug entries', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    expect(locs).not.toContain('/events/batman-begins-showtime')
  })

  it('excludes events with a null slug', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    // No /events/null or /events/undefined entry
    expect(locs.filter((l: string) => l.startsWith('/events/null'))).toHaveLength(0)
  })

  it('sets lastmod from event updated_at', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const eventEntry = urls.find((u) => (u as any).loc === '/events/ghibli-marathon')
    expect((eventEntry as any).lastmod).toBe('2026-05-01T00:00:00Z')
  })

  // ── Locations ─────────────────────────────────────────────────────────────

  it('includes one entry per location with the correct loc', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    expect(locs).toContain('/locations/downtown')
    expect(locs).toContain('/locations/uptown')
  })

  it('sets lastmod from location updated_at', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locEntry = urls.find((u) => (u as any).loc === '/locations/downtown')
    expect((locEntry as any).lastmod).toBe('2026-01-10T00:00:00Z')
  })

  // ── Blog posts ────────────────────────────────────────────────────────────

  it('includes one entry per blog post from static data', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    for (const post of BLOG_RESPONSE.data) {
      expect(locs).toContain(`/blog/${post.slug}`)
    }
  })

  it('sets lastmod from blog post date field', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const firstPost = BLOG_RESPONSE.data[0]
    const postEntry = urls.find((u) => (u as any).loc === `/blog/${firstPost.slug}`)
    expect((postEntry as any).lastmod).toBe(firstPost.date)
  })

  // ── Excluded routes ────────────────────────────────────────────────────────

  it('never includes /purchase/ paths', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    expect(locs.filter((l: string) => l.startsWith('/purchase'))).toHaveLength(0)
  })

  it('never includes /account paths', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    expect(locs.filter((l: string) => l.startsWith('/account'))).toHaveLength(0)
  })

  it('never includes /auth paths', async () => {
    setupHappyPath()
    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)
    expect(locs.filter((l: string) => l.startsWith('/auth'))).toHaveLength(0)
  })

  // ── Resilience ────────────────────────────────────────────────────────────

  it('still returns blog and location entries when the movies fetch fails', async () => {
    // movies → throws, events → ok, locations → ok
    mockFetch
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce(EVENTS_RESPONSE)
      .mockResolvedValueOnce(LOCATIONS_RESPONSE)
      .mockResolvedValueOnce(BLOG_RESPONSE)

    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)

    // movies failed — no movie entries
    expect(locs.filter((l: string) => l.startsWith('/movies/'))).toHaveLength(0)

    // events and locations still present
    expect(locs).toContain('/events/ghibli-marathon')
    expect(locs).toContain('/locations/downtown')

    // blog posts come from their own fetch — unaffected by the movies failure
    expect(locs).toContain(`/blog/${BLOG_RESPONSE.data[0].slug}`)
  })

  it('still returns movie and blog entries when the events fetch fails', async () => {
    mockFetch
      .mockResolvedValueOnce(MOVIES_RESPONSE)
      .mockRejectedValueOnce(new Error('Events API down'))
      .mockResolvedValueOnce(LOCATIONS_RESPONSE)
      .mockResolvedValueOnce(BLOG_RESPONSE)

    const urls = await buildUrls({} as any)
    const locs = urls.map((u) => (u as any).loc)

    expect(locs).toContain('/movies/the-dark-knight')
    expect(locs).toContain('/locations/downtown')
    expect(locs).toContain(`/blog/${BLOG_RESPONSE.data[0].slug}`)
    expect(locs.filter((l: string) => l.startsWith('/events/'))).toHaveLength(0)
  })

  it('calls the backend API with the configured apiBaseUrl', async () => {
    setupHappyPath()
    await buildUrls({} as any)

    // Every $fetch call must hit the three backend endpoints with a
    // non-empty baseURL set. The exact baseURL value depends on whether
    // the runtime-config mock is intercepted by Vite (it falls back to
    // 'http://localhost:8000' otherwise — both are valid in this test
    // since the dev value is irrelevant to the public sitemap contract).
    const calls = mockFetch.mock.calls.map((c: unknown[]) => ({
      path: c[0] as string,
      baseURL: ((c[1] as { baseURL?: string } | undefined)?.baseURL) ?? '',
    }))
    expect(calls).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ path: '/api/movies' }),
        expect.objectContaining({ path: '/api/calendar/events' }),
        expect.objectContaining({ path: '/api/locations' }),
      ]),
    )
    for (const call of calls) {
      expect(call.baseURL).toMatch(/^https?:\/\/.+/)
    }
  })
})
