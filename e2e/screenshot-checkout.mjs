/** Checkout-page capture for the design audit (companion to screenshot-audit.mjs). */
import { chromium } from 'playwright-core'
import { mkdirSync } from 'node:fs'

const BASE = process.env.BASE_URL || 'https://finalcut.test'
const OUT = '/app/test-results/screenshots-audit'
const WIDTHS = [
  { name: 'w0360', width: 360, height: 780 },
  { name: 'w0640', width: 640, height: 900 },
  { name: 'w0960', width: 960, height: 900 },
  { name: 'w1440', width: 1440, height: 900 },
]

const browser = await chromium.launch()
mkdirSync(OUT, { recursive: true })

// discover a purchase URL
const disco = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } })
const dpage = await disco.newPage()
await dpage.goto(`${BASE}/movies/fight-club`)
await dpage.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => {})
const purchaseHref = await dpage.locator('a[href*="/purchase/"]').first().getAttribute('href')
await disco.close()
console.log({ purchaseHref })

for (const vp of WIDTHS) {
  const ctx = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: vp.width, height: vp.height },
  })
  const page = await ctx.newPage()
  try {
    await page.goto(`${BASE}${purchaseHref}`, { timeout: 60_000 })
    await page.locator('[role="grid"]').waitFor({ timeout: 15_000 })
    await page.waitForTimeout(1500)
    const seats = page.locator('button.auditorium-seat:not(.auditorium-seat--taken):not(.auditorium-seat--held)')
    await seats.nth(0).dispatchEvent('click')
    await seats.nth(1).dispatchEvent('click')
    await page.waitForTimeout(500)
    await page.getByRole('button', { name: /continue to payment/i }).click()
    await page.waitForURL(/\/purchase\/checkout/, { timeout: 15_000 })
    await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => {})
    await page.waitForTimeout(2500)
    await page.screenshot({ path: `${OUT}/purchase-checkout--${vp.name}.png`, fullPage: true })
    console.log(`  ✓ purchase-checkout--${vp.name}`)
  } catch (e) {
    console.log(`  ✗ purchase-checkout--${vp.name}: ${e.message.split('\n')[0]}`)
  }
  await ctx.close()
}

await browser.close()
console.log('DONE')
