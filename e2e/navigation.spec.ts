import { test, expect } from '@playwright/test'
import { login } from './helpers/auth'
import { MOVIE_SLUGS } from './fixtures/test-data'

test.describe('Navigation', () => {
  test.describe('Header navigation', () => {
    const navLinks = [
      { name: 'Movies', path: '/movies' },
      { name: "What's On", path: '/whats-on' },
      { name: 'Food & Drink', path: '/food-drink' },
      { name: 'Events', path: '/events' },
      { name: 'Gift Cards', path: '/gift-cards' },
    ]

    for (const { name, path } of navLinks) {
      test(`clicking "${name}" navigates to ${path}`, async ({ page }) => {
        await page.goto('/')
        await page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name }).click()
        await expect(page).toHaveURL(new RegExp(path))
      })
    }
  })

  test('movie detail deep link renders movie title', async ({ page }) => {
    await page.goto(`/movies/${MOVIE_SLUGS.FIGHT_CLUB}`)
    await expect(page.locator('h1')).toContainText(/fight club/i)
  })

  test('calendar deep link with filters loads correct month', async ({ page }) => {
    await page.goto('/whats-on?month=4&year=2026&view=month')
    // Calendar should show April 2026
    await expect(page.locator('text=April').first()).toBeVisible({ timeout: 5_000 })
  })

  test('auth flow — login, access account, logout', async ({ page }) => {
    // Login
    await login(page)

    // Should be on account or home after login
    await expect(page).toHaveURL(/\/(account|$)/)

    // Navigate to account dashboard
    await page.goto('/account')
    await expect(page).toHaveURL(/\/account/)
    await expect(page.locator('h1, h2').first()).toBeVisible()

    // Logout — find logout in the header or account area
    const logoutBtn = page.getByRole('button', { name: /log\s?out|sign\s?out/i })
    if (await logoutBtn.isVisible()) {
      await logoutBtn.click()
    } else {
      // May need to open a dropdown first
      const avatarBtn = page.locator('[aria-label*="Account"]').first()
      if (await avatarBtn.isVisible()) {
        await avatarBtn.click()
        await page.getByRole('button', { name: /log\s?out|sign\s?out/i }).click()
      }
    }

    // After logout, should not be on account pages
    await page.waitForURL((url) => !url.pathname.startsWith('/account'), { timeout: 5_000 })
  })

  test('guest middleware — logged-in user redirected from login page', async ({ page }) => {
    await login(page)
    await page.goto('/auth/login')
    // Should redirect away from auth
    await page.waitForURL((url) => !url.pathname.startsWith('/auth/'), { timeout: 5_000 })
  })

  test('auth middleware — unauthenticated user redirected from account', async ({ page }) => {
    await page.goto('/account')
    // Should redirect to login with redirect param
    await page.waitForURL(/\/auth\/login/, { timeout: 5_000 })
  })

  test('404 handling for nonexistent movie', async ({ page }) => {
    const response = await page.goto('/movies/this-movie-does-not-exist-at-all')
    // Should show error page or 404 status
    const status = response?.status()
    if (status === 404) {
      // Direct 404 response
      return
    }
    // Nuxt may render a client-side error page
    await expect(
      page.locator('text=/not found|404|error/i').first()
    ).toBeVisible({ timeout: 5_000 })
  })

  test.describe('Layout transitions', () => {
    test('movies page uses default layout', async ({ page }) => {
      await page.goto('/movies')
      // Default layout has header, main, and footer
      await expect(page.locator('header[role="banner"]')).toBeVisible()
      await expect(page.locator('main#main-content')).toBeVisible()
      await expect(page.locator('footer[role="contentinfo"]')).toBeVisible()
    })

    test('login page uses blank layout', async ({ page }) => {
      await page.goto('/auth/login')
      // Blank layout — no header nav or footer
      await expect(page.locator('footer[role="contentinfo"]')).not.toBeVisible()
    })

    test('account page uses account layout with sidebar', async ({ page }) => {
      await login(page)
      await page.goto('/account')
      // Desktop + mobile sidebar navs share this label; `.first()`
      // avoids a strict-mode violation since both coexist in the DOM.
      await expect(page.locator('nav[aria-label="Account"]').first()).toBeVisible()
    })
  })
})
