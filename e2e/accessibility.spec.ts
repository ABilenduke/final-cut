import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { MOVIE_SLUGS } from './fixtures/test-data'

test.describe('Accessibility', () => {
  test('skip nav link moves focus to main content', async ({ page }) => {
    await page.goto('/')

    // Tab from the start of the page
    await page.keyboard.press('Tab')

    // Skip nav link should be focused and visible
    const skipLink = page.locator('a.skip-nav, a:has-text("Skip to main content")')
    await expect(skipLink).toBeFocused()
    await expect(skipLink).toBeVisible()

    // Activate the skip link
    await page.keyboard.press('Enter')

    // Focus should move to #main-content
    const main = page.locator('#main-content')
    await expect(main).toBeFocused()
  })

  test('landmark structure on home page', async ({ page }) => {
    await page.goto('/')

    // Verify required landmarks
    await expect(page.locator('header[role="banner"]')).toBeVisible()
    await expect(page.locator('nav[aria-label="Primary"]')).toBeVisible()
    await expect(page.locator('main#main-content')).toBeVisible()
    await expect(page.locator('footer[role="contentinfo"]')).toBeVisible()
  })

  test('seat grid keyboard navigation', async ({ page }) => {
    // Navigate to a showtime's seat selection
    await page.goto(`/movies/${MOVIE_SLUGS.INCEPTION}`)
    const showtimeLink = page.locator('a[href*="/purchase/"]').first()
    await expect(showtimeLink).toBeVisible({ timeout: 10_000 })
    await showtimeLink.click()
    await page.waitForURL(/\/purchase\//)

    const grid = page.locator('[role="grid"]')
    await expect(grid).toBeVisible({ timeout: 10_000 })

    // Tab into the seat grid
    const firstSeat = page.locator('button.auditorium-seat[tabindex="0"]')
    await firstSeat.focus()
    await expect(firstSeat).toBeFocused()

    // Arrow Right moves to the next seat
    await page.keyboard.press('ArrowRight')
    const focusedAfterRight = page.locator('button.auditorium-seat:focus')
    await expect(focusedAfterRight).toBeVisible()

    // Arrow Down moves to the next row
    await page.keyboard.press('ArrowDown')
    const focusedAfterDown = page.locator('button.auditorium-seat:focus')
    await expect(focusedAfterDown).toBeVisible()

    // Enter/Space toggles selection on an available seat
    const focusedSeat = page.locator('button.auditorium-seat:focus')
    const isTaken = await focusedSeat.getAttribute('aria-disabled')
    if (isTaken !== 'true') {
      await page.keyboard.press('Enter')
      await expect(focusedSeat).toHaveAttribute('aria-selected', 'true')

      // Press again to deselect
      await page.keyboard.press('Space')
      const selected = await focusedSeat.getAttribute('aria-selected')
      expect(selected).not.toBe('true')
    }

    // Escape deselects all and returns focus to grid container
    // First select a seat
    const availableSeat = page.locator('button.auditorium-seat:not([aria-disabled="true"])').first()
    await availableSeat.focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('Escape')

    const selectedAfterEscape = page.locator('button.auditorium-seat[aria-selected="true"]')
    await expect(selectedAfterEscape).toHaveCount(0)
  })

  test('accordion keyboard interaction on FAQ page', async ({ page }) => {
    await page.goto('/faq')

    // Scope to the FAQ content — the site header also has a mobile
    // hamburger with aria-expanded that would otherwise match first.
    const firstTrigger = page.locator('main button[aria-expanded]').first()
    await expect(firstTrigger).toBeVisible()

    // Focus and activate with Enter
    await firstTrigger.focus()
    const wasExpanded = await firstTrigger.getAttribute('aria-expanded')

    await page.keyboard.press('Enter')
    const isExpanded = await firstTrigger.getAttribute('aria-expanded')
    // Should have toggled
    expect(isExpanded).not.toBe(wasExpanded)

    // Toggle again with Space
    await page.keyboard.press('Space')
    const isExpandedAfterSpace = await firstTrigger.getAttribute('aria-expanded')
    expect(isExpandedAfterSpace).not.toBe(isExpanded)
  })

  test.describe('axe-core accessibility scans', () => {
    const publicPages = [
      { name: 'Home', path: '/' },
      { name: 'Movies', path: '/movies' },
      { name: 'Movie Detail', path: `/movies/${MOVIE_SLUGS.FIGHT_CLUB}` },
      { name: 'FAQ', path: '/faq' },
      { name: 'Contact', path: '/contact' },
    ]

    for (const { name, path } of publicPages) {
      test(`${name} page has no critical/serious violations`, async ({ page }) => {
        await page.goto(path)
        // Wait for page content to load
        await page.waitForLoadState('networkidle')

        const results = await new AxeBuilder({ page })
          .withTags(['wcag2a', 'wcag2aa'])
          .analyze()

        const critical = results.violations.filter(
          (v) => v.impact === 'critical' || v.impact === 'serious'
        )

        if (critical.length > 0) {
          const summary = critical.map(
            (v) => `[${v.impact}] ${v.id}: ${v.description} (${v.nodes.length} instances)`
          ).join('\n')
          expect(critical, `Accessibility violations on ${name}:\n${summary}`).toHaveLength(0)
        }
      })
    }
  })
})
