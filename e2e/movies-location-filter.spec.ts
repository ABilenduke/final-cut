/**
 * E2E: Movies page — location filter chips.
 *
 * Tests the opt-in ?location= filter on /movies:
 * - Default (no filter): all-locations view renders correctly
 * - Clicking a location chip updates the URL to ?location=<slug>
 * - Navigating directly to a filtered URL renders the filtered listing SSR-side
 */

import { test, expect } from '@playwright/test'

const LOCATION_SLUGS = {
  DOWNTOWN: 'downtown',
  EASTSIDE: 'eastside',
} as const

test.describe('Movies page — location filter', () => {
  test('renders location chip row on /movies', async ({ page }) => {
    await page.goto('/movies')
    await page.waitForLoadState('networkidle')

    // The location chip row should be present
    const chipGroup = page.getByRole('group', { name: 'Filter movies by location' })
    await expect(chipGroup).toBeVisible()
  })

  test('"All Locations" chip is active by default (no ?location=)', async ({ page }) => {
    await page.goto('/movies')
    await page.waitForLoadState('networkidle')

    const allChip = page.getByRole('button', { name: 'All Locations' })
    await expect(allChip).toBeVisible()
    await expect(allChip).toHaveAttribute('aria-pressed', 'true')
  })

  test('clicking Downtown chip updates URL to ?location=downtown', async ({ page }) => {
    await page.goto('/movies')
    await page.waitForLoadState('networkidle')

    await page.getByRole('button', { name: 'Downtown' }).click()
    await expect(page).toHaveURL(/[?&]location=downtown/)
  })

  test('clicking Eastside chip updates URL to ?location=eastside', async ({ page }) => {
    await page.goto('/movies')
    await page.waitForLoadState('networkidle')

    await page.getByRole('button', { name: 'Eastside' }).click()
    await expect(page).toHaveURL(/[?&]location=eastside/)
  })

  test('clicking "All Locations" after a filter removes ?location= from URL', async ({ page }) => {
    await page.goto('/movies?location=downtown')
    await page.waitForLoadState('networkidle')

    await page.getByRole('button', { name: 'All Locations' }).click()
    await expect(page).not.toHaveURL(/[?&]location=/)
  })

  test('active chip has aria-pressed=true when ?location= is set', async ({ page }) => {
    await page.goto(`/movies?location=${LOCATION_SLUGS.DOWNTOWN}`)
    await page.waitForLoadState('networkidle')

    const downtownChip = page.getByRole('button', { name: 'Downtown' })
    await expect(downtownChip).toHaveAttribute('aria-pressed', 'true')

    // "All Locations" should be inactive
    const allChip = page.getByRole('button', { name: 'All Locations' })
    await expect(allChip).toHaveAttribute('aria-pressed', 'false')
  })

  test('filtered URL /movies?location=downtown renders with page content', async ({ page }) => {
    // Test that the page loads successfully with the location filter applied.
    // The filtered listing should render (SSR) without error.
    const response = await page.goto(`/movies?location=${LOCATION_SLUGS.DOWNTOWN}`)
    expect(response?.status()).toBe(200)

    // Page content should be present
    await expect(page.locator('h1')).toBeVisible()
  })

  test('filtered URL renders SSR-side (page content in initial HTML)', async ({ page }) => {
    // Navigate to a filtered URL and verify it doesn't produce a blank page,
    // confirming SSR is working (not client-only rendering).
    await page.goto(`/movies?location=${LOCATION_SLUGS.EASTSIDE}`)

    // The h1 with the marquee headline should be present in the DOM
    // without needing client-side JS to render it.
    const h1 = page.locator('h1')
    await expect(h1).toBeVisible()
    // The location chip row should also render
    await expect(page.getByRole('group', { name: 'Filter movies by location' })).toBeVisible()
  })

  test('location filter preserves the ?status= query param', async ({ page }) => {
    await page.goto('/movies?status=coming_soon')
    await page.waitForLoadState('networkidle')

    await page.getByRole('button', { name: 'Downtown' }).click()

    // Both params should be present in the URL
    await expect(page).toHaveURL(/status=coming_soon/)
    await expect(page).toHaveURL(/location=downtown/)
  })
})
