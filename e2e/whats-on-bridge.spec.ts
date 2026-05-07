import { test, expect } from '@playwright/test'

/**
 * /whats-on Bridge Console — end-to-end smoke tests.
 *
 * Validate the redesigned page wiring against the running stack:
 *   - the page renders with toolbar + ribbon + grid + rail
 *   - clicking a day updates the rail
 *   - chip toggles round-trip through the URL
 *   - prev/next buttons rotate the month label
 *   - the today button restores the current month
 *   - keyboard arrow keys move the focused cell
 */
test.describe('What\'s On — Bridge Console', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await page.goto('/whats-on')
    await expect(page.locator('.bridge-toolbar')).toBeVisible()
  })

  test('renders the bridge layout chrome', async ({ page }) => {
    await expect(page.locator('.bridge-toolbar__title')).toContainText("What's")
    await expect(page.locator('.bridge-filter-ribbon')).toBeVisible()
    await expect(page.locator('.bridge-month-grid')).toBeVisible()
    await expect(page.locator('.bridge-detail-rail')).toBeVisible()
  })

  test('clicking a day cell updates the detail rail', async ({ page }) => {
    const cells = page.locator('.bridge-day-cell:not(.bridge-day-cell--muted)')
    const firstSelectedNum = await page
      .locator('.bridge-detail-rail .bridge-hero-card__day-num')
      .textContent()

    // Click a different non-muted cell — the 5th in-month day.
    await cells.nth(4).click()

    await expect
      .poll(async () =>
        (await page
          .locator('.bridge-detail-rail .bridge-hero-card__day-num')
          .textContent())?.trim(),
      )
      .not.toBe((firstSelectedNum ?? '').trim())

    await expect(page).toHaveURL(/[?&]date=\d{4}-\d{2}-\d{2}/)
  })

  test('toggling a chip persists to the URL and removes the active state', async ({ page }) => {
    const chip = page.locator('.cv-chip', { hasText: 'Members' })
    await expect(chip).toHaveClass(/cv-chip--active/)
    await chip.click()
    await expect(chip).not.toHaveClass(/cv-chip--active/)
    await expect(page).toHaveURL(/[?&]chips=/)
  })

  test('prev and next buttons rotate the visible month', async ({ page }) => {
    const monthLabel = page.locator('.bridge-month-grid__title')
    const initial = (await monthLabel.textContent())?.trim() ?? ''

    await page.locator('button[aria-label="Next month"]').click()
    await expect(monthLabel).not.toHaveText(initial)

    await page.locator('button[aria-label="Previous month"]').click()
    await expect(monthLabel).toHaveText(initial)
  })

  test('today button returns to the current month after navigating away', async ({ page }) => {
    const today = page.locator('.bridge-toolbar__today')
    const monthLabel = page.locator('.bridge-month-grid__title')
    const initial = (await monthLabel.textContent())?.trim() ?? ''

    await page.locator('button[aria-label="Next month"]').click()
    await page.locator('button[aria-label="Next month"]').click()
    await expect(monthLabel).not.toHaveText(initial)

    await today.click()
    await expect(monthLabel).toHaveText(initial)
  })

  test('focused cell responds to ArrowRight by moving selection', async ({ page }) => {
    // Find the currently selected cell and press ArrowRight.
    const selectedCell = page.locator('.bridge-day-cell--selected').first()
    const before = await selectedCell.getAttribute('data-date')
    await selectedCell.focus()
    await selectedCell.press('ArrowRight')

    // Selection moves via emit → updateQuery → navigateTo → reactive prop update.
    // That chain spans multiple microtasks, so poll until the DOM reflects it.
    await expect
      .poll(async () =>
        page.locator('.bridge-day-cell--selected').first().getAttribute('data-date'),
      )
      .not.toBe(before)
  })

  test('detail rail is hidden and drawer opens on tablet width', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 1024, height: 800 } })
    const page = await context.newPage()
    await page.goto('/whats-on')

    await expect(page.locator('.bridge-detail-rail')).toBeHidden()

    const cell = page
      .locator('.bridge-day-cell:not(.bridge-day-cell--muted)')
      .nth(2)
    await cell.click()

    await expect(page.locator('.bridge-drawer')).toBeVisible()
    await page.locator('.bridge-drawer__close').click()
    await expect(page.locator('.bridge-drawer')).toBeHidden()

    await context.close()
  })
})
