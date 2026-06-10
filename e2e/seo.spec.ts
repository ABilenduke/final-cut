import { test, expect } from '@playwright/test'
import { MOVIE_SLUGS } from './fixtures/test-data'

test.describe('SEO', () => {
  test.describe('Title tags', () => {
    const pages = [
      { path: '/', expected: /Final Cut.*Now Showing/ },
      { path: '/movies', expected: /Now Playing.*Final Cut|Coming Soon.*Final Cut|On the Marquee.*Final Cut/ },
      { path: `/movies/${MOVIE_SLUGS.FIGHT_CLUB}`, expected: /Fight Club.*Showtimes.*Final Cut/i },
      { path: '/faq', expected: /FAQ.*Final Cut/ },
      { path: '/contact', expected: /Visit Us.*Final Cut/ },
      { path: '/events', expected: /Events.*Final Cut/ },
      { path: '/food-drink', expected: /Food.*Drink.*Final Cut/ },
      { path: '/gift-cards', expected: /Gift Cards.*Final Cut/ },
      { path: '/whats-on', expected: /What.*On.*Final Cut/ },
    ]

    for (const { path, expected } of pages) {
      test(`${path} has correct title`, async ({ page }) => {
        await page.goto(path)
        await expect(page).toHaveTitle(expected)
      })
    }
  })

  test.describe('noindex on private pages', () => {
    const noindexPages = [
      '/auth/login',
      '/auth/register',
      '/auth/forgot-password',
      '/account',
      '/purchase/checkout',
    ]

    for (const path of noindexPages) {
      test(`${path} has noindex`, async ({ page }) => {
        const response = await page.goto(path)
        const xRobotsTag = response?.headers()['x-robots-tag']
        // Verify the route rule sets the X-Robots-Tag header
        expect(xRobotsTag).toBe('noindex')
      })
    }
  })

  test.describe('Structured data (JSON-LD)', () => {
    test('home page has ItemList schema', async ({ page }) => {
      await page.goto('/')
      await page.waitForLoadState('networkidle')

      const jsonLd = await page.evaluate(() => {
        const scripts = document.querySelectorAll('script[type="application/ld+json"]')
        for (const script of scripts) {
          try {
            const data = JSON.parse(script.textContent || '')
            if (data['@type'] === 'ItemList') return data
          } catch { /* skip invalid */ }
        }
        return null
      })

      expect(jsonLd).not.toBeNull()
      expect(jsonLd['@context']).toBe('https://schema.org')
      expect(jsonLd['@type']).toBe('ItemList')
    })

    test('FAQ page has FAQPage schema', async ({ page }) => {
      await page.goto('/faq')

      const jsonLd = await page.evaluate(() => {
        const scripts = document.querySelectorAll('script[type="application/ld+json"]')
        for (const script of scripts) {
          try {
            const data = JSON.parse(script.textContent || '')
            if (data['@type'] === 'FAQPage') return data
          } catch { /* skip invalid */ }
        }
        return null
      })

      expect(jsonLd).not.toBeNull()
      expect(jsonLd['@type']).toBe('FAQPage')
      expect(jsonLd.mainEntity).toBeDefined()
      expect(jsonLd.mainEntity.length).toBeGreaterThan(0)
    })

    test('contact page has LocalBusiness schema', async ({ page }) => {
      await page.goto('/contact')

      const jsonLd = await page.evaluate(() => {
        const scripts = document.querySelectorAll('script[type="application/ld+json"]')
        for (const script of scripts) {
          try {
            const data = JSON.parse(script.textContent || '')
            if (data['@type'] === 'LocalBusiness') return data
          } catch { /* skip invalid */ }
        }
        return null
      })

      expect(jsonLd).not.toBeNull()
      expect(jsonLd['@type']).toBe('LocalBusiness')
      // Per-venue schemas since admin-v2 Plan 14: "Final Cut — {Location}".
      // The brand prefix is the stable contract; the venue suffix varies.
      expect(jsonLd.name).toContain('Final Cut')
      expect(jsonLd.address?.['@type']).toBe('PostalAddress')
    })

    test('movie detail page has Movie schema', async ({ page }) => {
      await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
      await page.waitForLoadState('networkidle')

      const jsonLd = await page.evaluate(() => {
        const scripts = document.querySelectorAll('script[type="application/ld+json"]')
        for (const script of scripts) {
          try {
            const data = JSON.parse(script.textContent || '')
            if (data['@type'] === 'Movie') return data
          } catch { /* skip invalid */ }
        }
        return null
      })

      expect(jsonLd).not.toBeNull()
      expect(jsonLd['@type']).toBe('Movie')
      expect(jsonLd.name).toBeTruthy()
    })
  })

  test.describe('OG tags', () => {
    const ogPages = [
      { path: '/', title: /Final Cut/ },
      { path: '/movies', title: /Now Playing.*Final Cut|Coming Soon.*Final Cut|On the Marquee.*Final Cut/ },
      { path: `/movies/${MOVIE_SLUGS.FIGHT_CLUB}`, title: /Fight Club/i },
    ]

    for (const { path, title } of ogPages) {
      test(`${path} has OG tags`, async ({ page }) => {
        await page.goto(path)
        await page.waitForLoadState('networkidle')

        const ogTitle = await page.locator('meta[property="og:title"]').getAttribute('content')
        expect(ogTitle).toBeTruthy()
        expect(ogTitle).toMatch(title)

        const ogDescription = await page.locator('meta[property="og:description"]').getAttribute('content')
        expect(ogDescription).toBeTruthy()

        const ogType = await page.locator('meta[property="og:type"]').getAttribute('content')
        expect(ogType).toBeTruthy()
      })
    }

    test('movie detail has og:image', async ({ page }) => {
      await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
      await page.waitForLoadState('networkidle')

      const ogImage = await page.locator('meta[property="og:image"]').getAttribute('content')
      expect(ogImage).toBeTruthy()
    })
  })
})
