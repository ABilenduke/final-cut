/**
 * Playwright e2e tests for /sitemap.xml.
 *
 * These tests hit the live Nuxt dev server (https://finalcut.test) with the
 * full stack running (make up). The backend database is expected to be seeded
 * with the standard development seed set (make fresh).
 *
 * The test uses `request` (Playwright's HTTP context) rather than page.goto
 * because we need to inspect raw response headers and XML body without
 * browser rendering overhead or JavaScript execution.
 */

import { test, expect } from '@playwright/test'
import { MOVIE_SLUGS } from './fixtures/test-data'

test.describe('Sitemap', () => {
  test('GET /sitemap.xml returns HTTP 200', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    expect(response.status()).toBe(200)
  })

  test('GET /sitemap.xml returns XML content type', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const contentType = response.headers()['content-type'] ?? ''
    // Accept either application/xml or text/xml
    expect(contentType).toMatch(/(?:application|text)\/xml/)
  })

  test('sitemap.xml begins with XML declaration and urlset', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/^\s*<?xml/)
    expect(body).toContain('<urlset')
  })

  // ── Static pages ──────────────────────────────────────────────────────────

  test('sitemap.xml contains the homepage', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    // Matches both <loc>https://finalcut.test/</loc> and with trailing slash
    expect(body).toMatch(/<loc>[^<]*\/<\/loc>/)
  })

  test('sitemap.xml contains /movies', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toContain('<loc>')
    expect(body).toMatch(/<loc>[^<]*\/movies<\/loc>/)
  })

  test('sitemap.xml contains /locations', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/locations<\/loc>/)
  })

  test('sitemap.xml contains /food-drink', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/food-drink<\/loc>/)
  })

  test('sitemap.xml contains /contact', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/contact<\/loc>/)
  })

  test('sitemap.xml contains /faq', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/faq<\/loc>/)
  })

  test('sitemap.xml contains /whats-on', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/whats-on<\/loc>/)
  })

  test('sitemap.xml contains /events', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/events<\/loc>/)
  })

  test('sitemap.xml contains /gift-cards', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/gift-cards<\/loc>/)
  })

  test('sitemap.xml contains /private-screenings', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/private-screenings<\/loc>/)
  })

  test('sitemap.xml contains /accessibility', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/accessibility<\/loc>/)
  })

  test('sitemap.xml contains /careers', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/careers<\/loc>/)
  })

  test('sitemap.xml contains /blog', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toMatch(/<loc>[^<]*\/blog<\/loc>/)
  })

  // ── Dynamic pages (seeded slugs) ──────────────────────────────────────────

  test('sitemap.xml contains a seeded movie slug', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toContain(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
  })

  test('sitemap.xml contains the seeded downtown location', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toContain('/locations/downtown')
  })

  test('sitemap.xml contains the seeded uptown location', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).toContain('/locations/uptown')
  })

  // ── Excluded routes ────────────────────────────────────────────────────────
  // These pages carry X-Robots-Tag: noindex and robots: false in routeRules.
  // They must never appear in the sitemap.

  test('sitemap.xml does NOT contain /purchase/', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).not.toContain('/purchase/')
  })

  test('sitemap.xml does NOT contain /account', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).not.toContain('/account')
  })

  test('sitemap.xml does NOT contain /auth/', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    expect(body).not.toContain('/auth/')
  })

  // ── Entry count sanity check ──────────────────────────────────────────────
  // With the seeded dataset (8 now-showing + 2 coming-soon movies, 2 locations,
  // several events, 2 blog posts, 12+ static pages) there should be at least
  // 30 URL entries total. This catches regressions where the dynamic source
  // handler silently fails and the sitemap shrinks to only static routes.

  test('sitemap.xml contains at least 30 URL entries', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    const body = await response.text()
    const locMatches = body.match(/<loc>/g) ?? []
    expect(locMatches.length).toBeGreaterThanOrEqual(30)
  })
})
