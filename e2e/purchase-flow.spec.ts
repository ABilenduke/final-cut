import { test, expect } from '@playwright/test'
import { MOVIE_SLUGS, MAX_SEATS } from './fixtures/test-data'

test.describe('Purchase Flow', () => {
  test('full purchase flow — browse to confirmation', async ({ page }) => {
    // 1. Start at home page
    await page.goto('/')
    await expect(page).toHaveTitle(/Final Cut/)

    // 2. Navigate to a movie via the Now Showing grid
    const movieCard = page.locator('a[href*="/movies/"]').first()
    await movieCard.click()
    await page.waitForURL(/\/movies\//)

    // 3. Verify movie detail page rendered
    const movieTitle = page.locator('h1').first()
    await expect(movieTitle).toBeVisible()

    // 4. Click a showtime to enter purchase flow
    const showtimeLink = page.locator('a[href*="/purchase/"]').first()
    // Showtimes are client-only; wait for them to appear
    await expect(showtimeLink).toBeVisible({ timeout: 10_000 })
    await showtimeLink.click()
    await page.waitForURL(/\/purchase\//)

    // 5. Verify auditorium grid renders
    const grid = page.locator('[role="grid"]')
    await expect(grid).toBeVisible({ timeout: 10_000 })

    // 6. Select 2 available seats. Use force: true + the held-state
    // filter for the same reason as the other purchase-flow tests: the
    // seat-select animation (translateY + scale) renders the button
    // momentarily unstable, which occasionally defeats Playwright's
    // actionability wait on A-row seats.
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken):not(.auditorium-seat--held)')
    await expect(availableSeats.first()).toBeVisible()

    await availableSeats.nth(0).click({ force: true })
    await availableSeats.nth(1).click({ force: true })

    // 7. Verify cart updates
    const totalDisplay = page.locator('[aria-live="polite"]').first()
    await expect(totalDisplay).toBeVisible()

    // 8. Click Continue to concessions (step 2)
    const continueToConcessions = page.getByRole('button', { name: /continue to concessions/i })
    await expect(continueToConcessions).toBeEnabled()
    await continueToConcessions.click()
    await page.waitForURL(/\/purchase\/snacks/)

    // 9. Skip concessions and land on checkout
    const skipConcessions = page.getByRole('button', { name: /skip concessions/i })
    await expect(skipConcessions).toBeVisible({ timeout: 10_000 })
    await skipConcessions.click()
    await page.waitForURL(/\/purchase\/checkout/)

    // 10. Verify the checkout page has rendered
    await expect(page.getByRole('heading', { name: /finish the\s+booking/i })).toBeVisible({ timeout: 10_000 })
  })

  test('guest checkout shows email field', async ({ page }) => {
    // Navigate to a known movie and find a showtime
    await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
    const showtimeLink = page.locator('a[href*="/purchase/"]').first()
    await expect(showtimeLink).toBeVisible({ timeout: 10_000 })
    await showtimeLink.click()
    await page.waitForURL(/\/purchase\//)

    // Select enough seats to meet the default party size (2). Force past
    // the actionability check — the seat-select animation intermittently
    // defeats Playwright's stability wait on the first row.
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken):not(.auditorium-seat--held)')
    await expect(availableSeats.first()).toBeVisible({ timeout: 10_000 })
    await availableSeats.nth(0).click({ force: true })
    await availableSeats.nth(1).click({ force: true })

    // Continue to concessions, then skip to checkout
    await page.getByRole('button', { name: /continue to concessions/i }).click()
    await page.waitForURL(/\/purchase\/snacks/)
    await page.getByRole('button', { name: /skip concessions/i }).click()
    await page.waitForURL(/\/purchase\/checkout/)

    // Guest should see an email field. Narrow to the textbox role — the
    // promo-bay also exposes an "Email me a reel notice" checkbox which
    // otherwise makes the /email/i accessible-name lookup ambiguous.
    const emailInput = page.getByRole('textbox', { name: /email/i }).first()
    await expect(emailInput).toBeVisible()
  })

  test('empty cart redirects away from checkout', async ({ page }) => {
    // Navigate directly to checkout without selecting seats
    await page.goto('/purchase/checkout')

    // Should redirect — either to home or show an error state
    await page.waitForURL((url) => !url.pathname.includes('/purchase/checkout'), {
      timeout: 5_000,
    }).catch(() => {
      // If no redirect, the page should show an empty/error state
    })

    // Either redirected or shows no order content
    const url = page.url()
    if (url.includes('/purchase/checkout')) {
      // Still on checkout — verify there's no order summary with items
      const seatItems = page.locator('.checkout-page__seat-item')
      await expect(seatItems).toHaveCount(0)
    }
  })

  test('seat limit enforced', async ({ page }) => {
    await page.goto(`/movies/${MOVIE_SLUGS.DARK_KNIGHT}`)
    const showtimeLink = page.locator('a[href*="/purchase/"]').first()
    await expect(showtimeLink).toBeVisible({ timeout: 10_000 })
    await showtimeLink.click()
    await page.waitForURL(/\/purchase\//)

    const grid = page.locator('[role="grid"]')
    await expect(grid).toBeVisible({ timeout: 10_000 })

    // Try to select MAX_SEATS + 1 seats
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken):not(.auditorium-seat--held)')
    await expect(availableSeats.first()).toBeVisible({ timeout: 10_000 })
    const count = await availableSeats.count()
    const toSelect = Math.min(count, MAX_SEATS + 1)

    for (let i = 0; i < toSelect; i++) {
      // The auditorium grid has more seats than fit in the viewport, and
      // the seat-select animation (translateY + scale) briefly renders
      // the button unstable between clicks. Scroll each seat into view
      // and force past the actionability check.
      const seat = availableSeats.nth(i)
      await seat.scrollIntoViewIfNeeded()
      await seat.click({ force: true })
    }

    // The page caps selection at the current party size (which defaults to
    // 2 but may grow up to MAX_SEATS via the party-size control). Either
    // way, the final count must never exceed the MAX_SEATS hard limit.
    const selectedSeats = page.locator('button.auditorium-seat.auditorium-seat--selected')
    const selectedCount = await selectedSeats.count()
    expect(selectedCount).toBeLessThanOrEqual(MAX_SEATS)
  })

  test('back navigation preserves seat selections', async ({ page }) => {
    await page.goto(`/movies/${MOVIE_SLUGS.MATRIX}`)
    const showtimeLink = page.locator('a[href*="/purchase/"]').first()
    await expect(showtimeLink).toBeVisible({ timeout: 10_000 })
    await showtimeLink.click()
    await page.waitForURL(/\/purchase\//)

    // Select 2 seats. Use force: true to bypass the momentary instability
    // from the seat-select animation (translateY + scale) — the seat is
    // already confirmed visible, and the animation occasionally defeats
    // Playwright's actionability wait on A-row seats.
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken):not(.auditorium-seat--held)')
    await expect(availableSeats.first()).toBeVisible({ timeout: 10_000 })
    await availableSeats.nth(0).click({ force: true })
    await availableSeats.nth(1).click({ force: true })

    // Continue to concessions, then skip to checkout
    await page.getByRole('button', { name: /continue to concessions/i }).click()
    await page.waitForURL(/\/purchase\/snacks/)
    await page.getByRole('button', { name: /skip concessions/i }).click()
    await page.waitForURL(/\/purchase\/checkout/)

    // Navigate back via step indicator — step 1 label is "Seats"
    const step1 = page.locator('nav[aria-label="Purchase steps"]').getByText(/^seats$/i)
    await step1.click()
    await page.waitForURL(/\/purchase\/(?!checkout|snacks)/)

    // Verify seats are still selected
    const selectedSeats = page.locator('button.auditorium-seat.auditorium-seat--selected')
    await expect(selectedSeats).toHaveCount(2)
  })
})
