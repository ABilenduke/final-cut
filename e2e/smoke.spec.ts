import { test, expect } from '@playwright/test'

test('homepage loads and shows theater name', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveTitle(/Final Cut/i)
})
