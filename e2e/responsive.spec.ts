import { test, expect } from '@playwright/test'
import { login } from './helpers/auth'
import { VIEWPORTS, MOVIE_SLUGS } from './fixtures/test-data'

test.describe('Responsive Layout', () => {
  test.describe('Home page', () => {
    test('desktop — movie grid has multiple columns', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.desktop)
      await page.goto('/')
      const grid = page.locator('.ensemble').first()
      await expect(grid).toBeVisible()

      // Grid should have multiple columns at desktop width
      const gridColumns = await grid.evaluate((el) => {
        const style = window.getComputedStyle(el)
        return style.gridTemplateColumns
      })
      // Multiple column values means multi-column layout
      const columnCount = gridColumns.split(/\s+/).filter(Boolean).length
      expect(columnCount).toBeGreaterThan(1)
    })

    test('mobile — movie grid collapses to one or two columns', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.mobile)
      await page.goto('/')
      const grid = page.locator('.ensemble').first()
      await expect(grid).toBeVisible()

      const gridColumns = await grid.evaluate((el) => {
        const style = window.getComputedStyle(el)
        return style.gridTemplateColumns
      })
      const columnCount = gridColumns.split(/\s+/).filter(Boolean).length
      expect(columnCount).toBeLessThanOrEqual(2)
    })
  })

  test.describe('Movie detail', () => {
    test('desktop — hero grid has two columns (poster + title)', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.desktop)
      await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
      const layout = page.locator('.movie-hero__inner')
      await expect(layout).toBeVisible()

      const columns = await layout.evaluate((el) => {
        const style = window.getComputedStyle(el)
        return style.gridTemplateColumns
      })
      // Poster column (340px) + title column (1fr) renders as two resolved column tracks
      const columnCount = columns.split(/\s+/).filter(Boolean).length
      expect(columnCount).toBe(2)
    })

    test('mobile — hero grid collapses to single column', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.mobile)
      await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
      const layout = page.locator('.movie-hero__inner')
      await expect(layout).toBeVisible()

      const columns = await layout.evaluate((el) => {
        const style = window.getComputedStyle(el)
        return style.gridTemplateColumns || style.display
      })
      if (columns.includes('px') || columns.includes('fr')) {
        const columnCount = columns.split(/\s+/).filter(Boolean).length
        expect(columnCount).toBeLessThanOrEqual(1)
      }
    })
  })

  test.describe('Mobile nav', () => {
    test('visible at mobile width', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.mobile)
      await page.goto('/')
      const mobileNav = page.locator('nav[aria-label="Mobile navigation"]')
      await expect(mobileNav).toBeVisible()
    })

    test('hidden at desktop width', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.desktop)
      await page.goto('/')
      const mobileNav = page.locator('nav[aria-label="Mobile navigation"]')
      await expect(mobileNav).not.toBeVisible()
    })
  })

  test.describe('Account sidebar', () => {
    // Desktop and mobile sidebar navs share aria-label="Account" and
    // both live in the DOM; CSS hides one or the other based on
    // viewport. Scope the assertion to the variant expected to be
    // visible for each viewport instead of tripping strict-mode.
    test('desktop — full sidebar rail visible', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.desktop)
      await login(page)
      await page.goto('/account')
      const sidebar = page.locator('nav.sidebar-nav[aria-label="Account"]')
      await expect(sidebar).toBeVisible()

      // Should show labels (not just icons)
      const label = sidebar.locator('text=Dashboard').first()
      await expect(label).toBeVisible()
    })

    test('tablet — icon-only rail visible', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.tablet)
      await login(page)
      await page.goto('/account')
      const sidebar = page.locator('nav.sidebar-nav[aria-label="Account"]')
      await expect(sidebar).toBeVisible()
    })

    test('mobile — sidebar becomes bottom bar', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.mobile)
      await login(page)
      await page.goto('/account')
      // On mobile the bottom-bar variant is the one that renders.
      const nav = page.locator('nav.sidebar-nav-mobile[aria-label="Account"]')
      await expect(nav).toBeVisible()
    })
  })

  test.describe('No horizontal overflow', () => {
    const pages = [
      { name: 'Home', path: '/' },
      { name: 'Movies', path: '/movies' },
      { name: 'FAQ', path: '/faq' },
    ]

    for (const { name, path } of pages) {
      test(`${name} page has no horizontal overflow at mobile width`, async ({ page }) => {
        await page.setViewportSize(VIEWPORTS.mobile)
        await page.goto(path)
        await page.waitForLoadState('networkidle')

        const hasOverflow = await page.evaluate(() => {
          return document.documentElement.scrollWidth > window.innerWidth
        })
        expect(hasOverflow, `${name} page has horizontal overflow at ${VIEWPORTS.mobile.width}px`).toBe(false)
      })
    }
  })
})
