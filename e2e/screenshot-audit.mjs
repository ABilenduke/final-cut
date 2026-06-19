/**
 * One-off design-audit screenshot sweep. NOT part of the e2e suite.
 *
 * Run from repo root against the running dev stack:
 *   docker compose -f docker-compose.yml -f docker-compose.e2e.yml -f docker-compose.stack.yml \
 *     run --rm --no-deps playwright node screenshot-audit.mjs
 *
 * Full-page screenshots of every public page (+ auth, account, purchase flow)
 * at 4 audit widths. Output: e2e/test-results/screenshots-audit/.
 */
import { chromium } from 'playwright-core'
import { mkdirSync } from 'node:fs'

const BASE = process.env.BASE_URL || 'https://finalcut.test'
const OUT = '/app/test-results/screenshots-audit'
const TEST_USER_EMAIL = 'test@finalcut.test'
const TEST_USER_PASSWORD = 'password'

const WIDTHS = [
  { name: 'w0360', width: 360, height: 780 },
  { name: 'w0640', width: 640, height: 900 },
  { name: 'w0960', width: 960, height: 900 },
  { name: 'w1440', width: 1440, height: 900 },
]

const STATIC_ROUTES = [
  ['home', '/'],
  ['movies', '/movies'],
  ['whats-on', '/whats-on'],
  ['events', '/events'],
  ['food-drink', '/food-drink'],
  ['locations', '/locations'],
  ['gift-cards', '/gift-cards'],
  ['gift-cards-bulk', '/gift-cards/bulk'],
  ['contact', '/contact'],
  ['faq', '/faq'],
  ['careers', '/careers'],
  ['accessibility', '/accessibility'],
  ['private-screenings', '/private-screenings'],
  ['blog', '/blog'],
  ['terms', '/terms'],
  ['privacy', '/privacy'],
  ['movie-detail', '/movies/fight-club'],
  ['auth-login', '/auth/login'],
  ['auth-register', '/auth/register'],
  ['auth-forgot', '/auth/forgot-password'],
]

const ACCOUNT_ROUTES = [
  ['account-dashboard', '/account'],
  ['account-orders', '/account/orders'],
  ['account-bookings', '/account/bookings'],
  ['account-loyalty', '/account/loyalty'],
  ['account-payments', '/account/payment-methods'],
  ['account-profile', '/account/profile'],
]

async function settle(page, ms = 1500) {
  await page.waitForLoadState('domcontentloaded')
  await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => {})
  await page.waitForTimeout(ms)
}

async function shoot(page, name, widthName) {
  await page.screenshot({ path: `${OUT}/${name}--${widthName}.png`, fullPage: true })
  console.log(`  ✓ ${name}--${widthName}`)
}

async function firstHref(page, route, selector) {
  await page.goto(`${BASE}${route}`)
  await settle(page)
  const href = await page.locator(selector).first().getAttribute('href').catch(() => null)
  return href
}

const browser = await chromium.launch()
mkdirSync(OUT, { recursive: true })

// Discover dynamic detail routes once (desktop context)
const discoCtx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } })
const disco = await discoCtx.newPage()
const eventHref = await firstHref(disco, '/events', 'a[href^="/events/"]')
const locationHref = await firstHref(disco, '/locations', 'a[href^="/locations/"]')
const blogHref = await firstHref(disco, '/blog', 'a[href^="/blog/"]')
const purchaseHref = await firstHref(disco, '/movies/fight-club', 'a[href*="/purchase/"]')
await discoCtx.close()
console.log({ eventHref, locationHref, blogHref, purchaseHref })

const DYNAMIC_ROUTES = [
  eventHref && ['event-detail', eventHref],
  locationHref && ['location-detail', locationHref],
  blogHref && ['blog-post', blogHref],
  purchaseHref && ['purchase-seats', purchaseHref],
].filter(Boolean)

for (const vp of WIDTHS) {
  console.log(`── ${vp.name} ──`)

  // Logged-out sweep
  const ctx = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: vp.width, height: vp.height },
  })
  const page = await ctx.newPage()
  for (const [name, route] of [...STATIC_ROUTES, ...DYNAMIC_ROUTES]) {
    try {
      await page.goto(`${BASE}${route}`, { timeout: 60_000 })
      await settle(page, name === 'purchase-seats' ? 3000 : 1500)
      await shoot(page, name, vp.name)
    } catch (e) {
      console.log(`  ✗ ${name}: ${e.message.split('\n')[0]}`)
    }
  }

  // Checkout: select a seat on the seat map, continue
  if (purchaseHref) {
    try {
      await page.goto(`${BASE}${purchaseHref}`, { timeout: 60_000 })
      await settle(page, 3000)
      const seat = page.locator('[role="gridcell"]:not([aria-disabled="true"])').first()
      await seat.click({ timeout: 10_000 })
      await page.getByRole('link', { name: /checkout|continue/i }).or(
        page.getByRole('button', { name: /checkout|continue/i })
      ).first().click({ timeout: 10_000 })
      await page.waitForURL('**/purchase/checkout', { timeout: 15_000 })
      await settle(page, 2500)
      await shoot(page, 'purchase-checkout', vp.name)
    } catch (e) {
      console.log(`  ✗ purchase-checkout: ${e.message.split('\n')[0]}`)
    }
  }
  await ctx.close()

  // Logged-in account sweep
  const authCtx = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: vp.width, height: vp.height },
  })
  const authPage = await authCtx.newPage()
  try {
    await authPage.goto(`${BASE}/auth/login`, { timeout: 60_000 })
    await settle(authPage)
    await authPage.getByLabel('Email').fill(TEST_USER_EMAIL)
    await authPage.getByLabel('Password').fill(TEST_USER_PASSWORD)
    await authPage.getByRole('button', { name: /sign in/i }).click()
    await authPage.waitForURL((url) => !url.pathname.startsWith('/auth/'), { timeout: 15_000 })
    for (const [name, route] of ACCOUNT_ROUTES) {
      try {
        await authPage.goto(`${BASE}${route}`, { timeout: 60_000 })
        await settle(authPage)
        await shoot(authPage, name, vp.name)
      } catch (e) {
        console.log(`  ✗ ${name}: ${e.message.split('\n')[0]}`)
      }
    }
  } catch (e) {
    console.log(`  ✗ login failed at ${vp.name}: ${e.message.split('\n')[0]}`)
  }
  await authCtx.close()
}

await browser.close()
console.log('DONE')
