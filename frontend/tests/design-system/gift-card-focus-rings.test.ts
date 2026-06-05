import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

/**
 * WCAG 2.4.7 guard: the gift-card custom inputs strip the native `outline`
 * for styling, so they MUST provide a `:focus-visible` replacement. A
 * pseudo-class focus ring isn't observable in jsdom, so assert at the source
 * level that the rule exists (and can't silently regress).
 *
 * `__dirname` resolves under the Nuxt Vitest environment (see no-chrome-hex.test.ts).
 */
const COMPONENTS_ROOT = resolve(__dirname, '../../app/components')

const read = (rel: string): string => readFileSync(resolve(COMPONENTS_ROOT, rel), 'utf-8')

describe('gift-card inputs expose keyboard focus indicators', () => {
  it('GiftCardComposer custom-amount input has a :focus-visible ring', () => {
    expect(read('content/GiftCardComposer.vue')).toMatch(/\.composer__custom-input:focus-visible/)
  })

  it('GiftCardBalanceStrip input and button have :focus-visible rings', () => {
    const css = read('content/GiftCardBalanceStrip.vue')
    expect(css).toMatch(/\.gift-card-balance-strip__input:focus-visible/)
    expect(css).toMatch(/\.gift-card-balance-strip__btn:focus-visible/)
  })
})
