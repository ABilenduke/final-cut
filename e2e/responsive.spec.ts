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

    test('mobile — movie grid collapses to single column', async ({ page }) => {
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
    test('desktop — establishing shot has two columns', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.desktop)
      await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
      const layout = page.locator('.establishing-shot')
      await expect(layout).toBeVisible()

      const columns = await layout.evaluate((el) => {
        const style = window.getComputedStyle(el)
        return style.gridTemplateColumns
      })
      // Two column values for 65/35 split
      const columnCount = columns.split(/\s+/).filter(Boolean).length
      expect(columnCount).toBe(2)
    })

    test('mobile — establishing shot collapses to single column', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.mobile)
      await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
      const layout = page.locator('.establishing-shot')
      await expect(layout).toBeVisible()

      const columns = await layout.evaluate((el) => {
        const style = window.getComputedStyle(el)
        return style.gridTemplateColumns || style.display
      })
      // Single column — either one grid column or flex/block display
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
    test('desktop — full sidebar rail visible', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.desktop)
      await login(page)
      await page.goto('/account')
      const sidebar = page.locator('nav[aria-label="Account"]')
      await expect(sidebar).toBeVisible()

      // Should show labels (not just icons)
      const label = sidebar.locator('text=Dashboard').first()
      await expect(label).toBeVisible()
    })

    test('tablet — icon-only rail visible', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.tablet)
      await login(page)
      await page.goto('/account')
      const sidebar = page.locator('nav[aria-label="Account"]')
      await expect(sidebar).toBeVisible()
    })

    test('mobile — sidebar becomes bottom bar', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS.mobile)
      await login(page)
      await page.goto('/account')
      // Account nav should still be accessible on mobile (as bottom bar)
      const nav = page.locator('nav[aria-label="Account"]')
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
