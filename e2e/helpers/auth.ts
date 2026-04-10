import type { Page } from '@playwright/test'
import { TEST_USER_EMAIL, TEST_USER_PASSWORD } from '../fixtures/test-data'

/**
 * Log in via the UI login form.
 * Navigates to /auth/login, fills credentials, submits, and waits for redirect.
 */
export async function login(
  page: Page,
  email = TEST_USER_EMAIL,
  password = TEST_USER_PASSWORD,
): Promise<void> {
  await page.goto('/auth/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password').fill(password)
  await page.getByRole('button', { name: /sign in/i }).click()
  // Wait for redirect away from auth pages
  await page.waitForURL((url) => !url.pathname.startsWith('/auth/'), { timeout: 10_000 })
}

/**
 * Log out via the UI.
 */
export async function logout(page: Page): Promise<void> {
  // Click the user menu / logout button
  await page.getByRole('button', { name: /log\s?out|sign\s?out/i }).click()
  await page.waitForURL(
    (url) => url.pathname !== '/account' && !url.pathname.startsWith('/account/'),
    { timeout: 10_000 },
  )
}
