import { test, expect } from '@playwright/test'

/**
 * HomeFeaturedCarousel — end-to-end tests.
 *
 * These tests run against the full stack (make e2e) with seeded slides.
 * Backend Task 4 seeds 4 slides: Festival Week, Now Showing, Premier
 * Members Night, Gift Cards.
 */

test.describe('HomeFeaturedCarousel', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
  })

  // ── Auto-advance ──────────────────────────────────────────────────────────

  test('auto-advances once within 7.5 seconds', async ({ page }) => {
    // Find the first active slide and note which one it is
    const firstActive = page.locator('.carousel__slide--active').first()
    const initialHeadline = await firstActive.locator('.carousel__headline').textContent()

    // Wait slightly beyond the 7s interval
    await page.waitForTimeout(7500)

    // The active slide should have changed
    const nowActive = page.locator('.carousel__slide--active').first()
    const newHeadline = await nowActive.locator('.carousel__headline').textContent()

    expect(newHeadline).not.toBe(initialHeadline)
  })

  // ── Hover pauses auto-advance ─────────────────────────────────────────────

  test('hover pauses auto-advance', async ({ page }) => {
    const carousel = page.locator('.carousel')

    // Get initial active slide headline
    const firstActive = page.locator('.carousel__slide--active').first()
    const initialHeadline = await firstActive.locator('.carousel__headline').textContent()

    // Hover over the carousel — this should pause auto-advance
    await carousel.hover()

    // Wait beyond the 7s interval — slide should NOT have changed
    await page.waitForTimeout(7500)

    const nowActive = page.locator('.carousel__slide--active').first()
    const headlineAfterWait = await nowActive.locator('.carousel__headline').textContent()

    expect(headlineAfterWait).toBe(initialHeadline)
  })

  // ── Keyboard navigation ───────────────────────────────────────────────────

  test('Enter on next button advances the slide', async ({ page }) => {
    const firstActive = page.locator('.carousel__slide--active').first()
    const initialHeadline = await firstActive.locator('.carousel__headline').textContent()

    // Focus the next button and press Enter
    const nextBtn = page.locator('.carousel__nav-btn--next')
    await nextBtn.focus()
    await nextBtn.press('Enter')

    const nowActive = page.locator('.carousel__slide--active').first()
    const newHeadline = await nowActive.locator('.carousel__headline').textContent()

    expect(newHeadline).not.toBe(initialHeadline)
  })

  test('clicking a dot navigates to that slide', async ({ page }) => {
    const dots = page.locator('.carousel__dot')
    const count = await dots.count()

    if (count > 1) {
      // Click the last dot
      await dots.last().click()

      // Verify aria-current moved to the last dot
      const lastDot = dots.last()
      await expect(lastDot).toHaveAttribute('aria-current', 'true')
    }
  })

  // ── Reduced motion ────────────────────────────────────────────────────────

  test('auto-advance is disabled under prefers-reduced-motion: reduce', async ({ browser }) => {
    // Open a fresh page with the reduced-motion preference emulated
    const context = await browser.newContext({
      reducedMotion: 'reduce',
    })
    const page = await context.newPage()
    await page.goto('/')

    const firstActive = page.locator('.carousel__slide--active').first()
    const initialHeadline = await firstActive.locator('.carousel__headline').textContent()

    // Wait beyond the 7s interval — should NOT advance
    await page.waitForTimeout(7500)

    const nowActive = page.locator('.carousel__slide--active').first()
    const headlineAfterWait = await nowActive.locator('.carousel__headline').textContent()

    expect(headlineAfterWait).toBe(initialHeadline)

    await context.close()
  })

  // ── Accessibility ─────────────────────────────────────────────────────────

  test('carousel region has correct ARIA attributes', async ({ page }) => {
    const carousel = page.locator('[role="region"][aria-roledescription="carousel"]')
    await expect(carousel).toBeVisible()
    await expect(carousel).toHaveAttribute('aria-label', 'Featured')
  })

  test('first slide panel has role=group and aria-roledescription=slide', async ({ page }) => {
    const firstPanel = page.locator('[role="group"][aria-roledescription="slide"]').first()
    await expect(firstPanel).toBeAttached()
  })
})
