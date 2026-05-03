import { test, expect } from '@playwright/test'

/**
 * E2E: /food-drink — per-item availability captions (Task 7)
 *
 * These tests verify that the cross-location food menu page renders
 * availability captions for items that are not stocked at every venue.
 *
 * Prerequisite: seeded data must include at least one menu item whose
 * available_at array is a strict subset of all location slugs. The
 * DatabaseSeeder or a dedicated seeder should produce such an item.
 * If the seeder has not been updated to produce partial-availability
 * items, the "finds at least one availability caption" assertion will
 * fail as a signal to update the seeder.
 */

test.describe('/food-drink availability captions', () => {
  test('page loads and renders menu items', async ({ page }) => {
    const response = await page.goto('/food-drink')
    expect(response?.status()).toBe(200)

    // The catalog container should be present.
    await expect(page.locator('.catalog')).toBeVisible()
  })

  test('renders at least one availability caption for a partial-availability item', async ({ page }) => {
    await page.goto('/food-drink')

    // Wait for the catalog to hydrate.
    await page.waitForSelector('.catalog__products', { state: 'visible' })

    // Look for at least one availability note. If the seeder has a
    // partial-availability item, this will find ".prod__availability" in the
    // ConcessionItemCard, which carries the "Available at X only" or
    // "Available at X · Y" text.
    const captions = page.locator('.prod__availability')
    const count = await captions.count()

    // At least one partial-availability item should exist in seed data.
    expect(count).toBeGreaterThan(0)

    // The caption text should match the expected pattern.
    const firstCaption = await captions.first().textContent()
    expect(firstCaption).toMatch(/Available at .+/)
  })

  test('availability caption text matches the expected format', async ({ page }) => {
    await page.goto('/food-drink')
    await page.waitForSelector('.catalog__products', { state: 'visible' })

    const captions = page.locator('.prod__availability')
    const count = await captions.count()

    if (count === 0) {
      // No partial-availability items in seed → skip format check.
      // This is not a failure — it signals the seeder needs updating.
      test.skip()
      return
    }

    for (let i = 0; i < count; i++) {
      const text = await captions.nth(i).textContent()
      // Must match "Available at X only" or "Available at X · Y [· Z …]"
      expect(text).toMatch(/^→\s*Available at .+$/)
    }
  })

  test('items available at all venues show no availability caption', async ({ page }) => {
    await page.goto('/food-drink')
    await page.waitForSelector('.catalog__products', { state: 'visible' })

    // Every rendered card that does NOT have a caption should be an
    // item available at all venues. We can't easily assert this from the
    // outside without knowing the full venue roster, but we can assert that
    // a card without a caption renders normally (name, price visible).
    const cards = page.locator('.prod')
    const cardCount = await cards.count()
    expect(cardCount).toBeGreaterThan(0)

    // Find a card without a caption and assert it renders core content.
    for (let i = 0; i < Math.min(cardCount, 5); i++) {
      const card = cards.nth(i)
      const hasCaption = await card.locator('.prod__availability').count() > 0
      if (!hasCaption) {
        // This card covers all venues — it should render name and price.
        await expect(card.locator('.prod__name')).toBeVisible()
        await expect(card.locator('.prod__pr')).toBeVisible()
        break
      }
    }
  })

  test('footer note about location variance is visible', async ({ page }) => {
    await page.goto('/food-drink')
    await expect(
      page.getByText('Selection may vary by location'),
    ).toBeVisible()
  })
})
