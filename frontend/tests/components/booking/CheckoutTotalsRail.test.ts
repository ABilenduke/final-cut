import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CheckoutTotalsRail from '~/components/booking/CheckoutTotalsRail.vue'

const baseProps = {
  seats: [
    { seatId: 'F7', section: 'Standard', price: 1850 },
    { seatId: 'F8', section: 'Standard', price: 1850 },
  ],
  foodItems: [
    { itemId: 'pop', name: 'Popcorn', quantity: 1, unitPrice: 900 },
  ],
  promoCode: null,
  promoDiscount: 0,
  giftCardAmount: 0,
  subtotal: 4600, // 2*1850 + 900
  total: 4600,
  timeRemaining: 462,
}

describe('CheckoutTotalsRail', () => {
  it('renders the § Ω numbered header', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    expect(wrapper.find('.bay__number').text()).toBe('§ Ω')
  })

  it('shows subtotal, booking fee, tax, and grand total lines', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    const text = wrapper.text()
    expect(text).toContain('Subtotal')
    expect(text).toContain('Booking fee')
    expect(text).toContain('Tax')
    expect(text).toContain('Total due')
    // Grand = 4600 + 150 + round(4600*0.0725) = 4600 + 150 + 334 = 5084
    expect(wrapper.find('.totals-rail__grand-v').text()).toBe('$50.84')
  })

  it('shows promo discount line when promoDiscount > 0', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, {
      props: { ...baseProps, promoCode: 'REEL', promoDiscount: 400 },
    })
    const text = wrapper.text()
    expect(text).toContain('Member discount')
    expect(text).toContain('REEL')
    expect(wrapper.find('.totals-rail__v--neg').text()).toContain('−$4.00')
  })

  it('emits submit when the pay button is clicked', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    const payBtn = wrapper.find('.totals-rail__pay')
    expect(payBtn.attributes('disabled')).toBeUndefined()
    await payBtn.trigger('click')
    expect(wrapper.emitted('submit')).toBeDefined()
  })

  it('disables the pay button when seats array is empty', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, {
      props: { ...baseProps, seats: [], foodItems: [], subtotal: 0, total: 0 },
    })
    expect(wrapper.find('.totals-rail__pay').attributes('disabled')).toBeDefined()
  })

  it('disables the pay button when disabled prop is true', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, {
      props: { ...baseProps, disabled: true },
    })
    expect(wrapper.find('.totals-rail__pay').attributes('disabled')).toBeDefined()
  })

  it('mirrors the hold timer in the authorization note', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    expect(wrapper.find('.totals-rail__note').text()).toContain('07:42')
  })

  it('renders all three trust badges', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    const trust = wrapper.find('.totals-rail__trust').text()
    expect(trust).toContain('TLS 1.3')
    expect(trust).toContain('PCI-DSS')
    expect(trust).toContain('3-D Secure')
  })

  it('shows the Reel Society upsell card', async () => {
    const wrapper = await mountSuspended(CheckoutTotalsRail, { props: baseProps })
    expect(wrapper.find('.totals-rail__upsell').text()).toContain('Reel Society')
  })
})
