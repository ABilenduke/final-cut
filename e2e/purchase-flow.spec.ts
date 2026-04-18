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

    // 6. Select 2 available seats
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken)')
    await expect(availableSeats.first()).toBeVisible()

    await availableSeats.nth(0).click()
    await availableSeats.nth(1).click()

    // 7. Verify cart updates
    const totalDisplay = page.locator('[aria-live="polite"]').first()
    await expect(totalDisplay).toBeVisible()

    // 8. Click Continue to Checkout
    const continueBtn = page.getByRole('button', { name: /continue to checkout/i })
    await expect(continueBtn).toBeEnabled()
    await continueBtn.click()
    await page.waitForURL(/\/purchase\/checkout/)

    // 9. Verify checkout page shows order summary
    await expect(page.locator('text=Order Summary').first()).toBeVisible({ timeout: 5_000 })
  })

  test('guest checkout shows email field', async ({ page }) => {
    // Navigate to a known movie and find a showtime
    await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
    const showtimeLink = page.locator('a[href*="/purchase/"]').first()
    await expect(showtimeLink).toBeVisible({ timeout: 10_000 })
    await showtimeLink.click()
    await page.waitForURL(/\/purchase\//)

    // Select a seat
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken)')
    await expect(availableSeats.first()).toBeVisible({ timeout: 10_000 })
    await availableSeats.first().click()

    // Continue to checkout
    const continueBtn = page.getByRole('button', { name: /continue to checkout/i })
    await continueBtn.click()
    await page.waitForURL(/\/purchase\/checkout/)

    // Guest should see an email field
    const emailInput = page.getByLabel(/email/i)
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

    // Select MAX_SEATS seats
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken)')
    const count = await availableSeats.count()
    const toSelect = Math.min(count, MAX_SEATS + 1)

    for (let i = 0; i < toSelect; i++) {
      await availableSeats.nth(i).click()
    }

    // Verify only MAX_SEATS are selected
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

    // Select 2 seats
    const availableSeats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken)')
    await expect(availableSeats.first()).toBeVisible({ timeout: 10_000 })
    await availableSeats.nth(0).click()
    await availableSeats.nth(1).click()

    // Continue to checkout
    const continueBtn = page.getByRole('button', { name: /continue to checkout/i })
    await continueBtn.click()
    await page.waitForURL(/\/purchase\/checkout/)

    // Navigate back via step indicator
    const step1 = page.locator('nav[aria-label="Purchase steps"]').getByText(/pick your seats/i)
    await step1.click()
    await page.waitForURL(/\/purchase\/(?!checkout)/)

    // Verify seats are still selected
    const selectedSeats = page.locator('button.auditorium-seat.auditorium-seat--selected')
    await expect(selectedSeats).toHaveCount(2)
  })
})
