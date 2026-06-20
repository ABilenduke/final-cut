import { describe, it, expect } from 'vitest'

/**
 * The sitemap URL surface is defined by the architecture doc
 * (docs/architecture/SITE_ARCHITECTURE.md § Sitemap). This test pins
 * the static portion so the hand-rolled replacement in
 * server/routes/sitemap.xml.ts cannot silently lose pages.
 *
 * Dynamic URLs (movies, events, locations, blog) are sourced from
 * /api/__sitemap__/urls — covered by sitemap-urls.test.ts.
 */
describe('sitemap static URL contract', () => {
  const STATIC_URLS = [
    '/',
    '/movies',
    '/whats-on',
    '/events',
    '/food-drink',
    '/locations',
    '/faq',
    '/contact',
    '/accessibility',
    '/careers',
    '/gift-cards',
    '/private-screenings',
    '/terms',
    '/privacy',
    '/blog',
  ] as const

  const EXCLUDED_URLS = [
    '/purchase/',
    '/account',
    '/auth/',
  ] as const

  it('locks the static URL set the sitemap must emit', () => {
    expect([...STATIC_URLS].sort()).toMatchSnapshot()
  })

  it('locks the excluded URL set', () => {
    expect([...EXCLUDED_URLS].sort()).toMatchSnapshot()
  })
})
