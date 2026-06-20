import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CheckoutTotalsRail from '~/components/booking/CheckoutTotalsRail.vue'

// The rail is now a pure totals summary — the Confirm & pay button, the
// authorization note, and the trust badges moved to CheckoutConfirmBay (so
// they render in the main column and stay reachable on mobile, where the rail
// is hidden). Those behaviors are covered in CheckoutConfirmBay.test.ts.
const baseProps = {
  seats: [
    { id: 'uuid-f7', label: 'F7', section: 'Standard', price: 1850 },
    { id: 'uuid-f8', label: 'F8', section: 'Standard', price: 1850 },
  ],
  promoCode: null,
  promoDiscount: 0,
  giftCardAmount: 0,
  subtotal: 3700, // 2 × 1850 — seats only; concessions moved off the booking flow.
  total: 3700,
}

describe('CheckoutTotalsRail', () => {
  it('renders the § Ω numbered header', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    expect(wrapper.find('.bay__number').text()).toBe('§ Ω')
  })

  it('shows subtotal and grand total matching the authoritative total prop', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    const text = wrapper.text()
    expect(text).toContain('Subtotal')
    expect(text).toContain('Total due')
    expect(text).not.toContain('Booking fee')
    expect(text).not.toContain('Tax')
    // No Concessions line — those live on /food-drink, not in the booking rail.
    expect(text).not.toContain('Concessions')
    expect(wrapper.find('.totals-rail__grand-v').text()).toBe('$37.00')
  })

  it('reflects promo discount in the grand total via the total prop', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, {
      props: { ...baseProps, promoCode: 'REEL', promoDiscount: 400, total: 3300 },
    })
    const text = wrapper.text()
    expect(text).toContain('Member discount')
    expect(text).toContain('REEL')
    expect(wrapper.find('.totals-rail__v--neg').text()).toContain('−$4.00')
    expect(wrapper.find('.totals-rail__grand-v').text()).toBe('$33.00')
  })

  it('reflects gift card redemption in the grand total via the total prop', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, {
      props: { ...baseProps, giftCardAmount: 1000, total: 2700 },
    })
    expect(wrapper.find('.totals-rail__grand-v').text()).toBe('$27.00')
    expect(wrapper.text()).toContain('Gift card')
  })

  it('no longer renders the pay button (moved to CheckoutConfirmBay)', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    expect(wrapper.find('.totals-rail__pay').exists()).toBe(false)
  })

  it('shows the Reel Society upsell card', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    expect(wrapper.find('.totals-rail__upsell').text()).toContain('Reel Society')
  })
})
