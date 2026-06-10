import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// The frontend promises seats for SESSION_HOLD_MINUTES; the backend sweeper
// (bookings:expire-held, default --minutes=20) garbage-collects Held
// bookings older than its threshold. If the sweeper threshold ever drops to
// (or below) the frontend window, in-flight checkouts can be swept
// mid-payment.
//
// The test containers can't read across the frontend/backend mount boundary,
// so each side pins ITS OWN value to the shared contract: frontend hold = 8,
// backend sweep = 20 (≥ hold + 2min safety margin). The backend half lives in
// backend/tests/Unit/HoldTimerContractTest.php — change either value and the
// matching pin fails, pointing you here (admin-v3 Plan 04).
const HOLD_CONTRACT_MINUTES = 8
const SWEEP_CONTRACT_MINUTES = 20

const CART = readFileSync(
  resolve(__dirname, '../../app/composables/useCart.ts'),
  'utf8',
)

describe('hold timer alignment', () => {
  it('pins the frontend hold window to the cross-stack contract', () => {
    const match = CART.match(/SESSION_HOLD_MINUTES = (\d+)/)
    expect(match).not.toBeNull()
    expect(Number(match![1])).toBe(HOLD_CONTRACT_MINUTES)
  })

  it('keeps the documented sweep threshold at least 2 minutes above the hold window', () => {
    expect(SWEEP_CONTRACT_MINUTES).toBeGreaterThanOrEqual(HOLD_CONTRACT_MINUTES + 2)
  })
})
