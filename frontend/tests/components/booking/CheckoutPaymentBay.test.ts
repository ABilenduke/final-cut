import { describe, it, expect, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CheckoutPaymentBay from '~/components/booking/CheckoutPaymentBay.vue'

// Stripe.js loads from a CDN inside `loadStripe`. In a JSDOM test environment it
// returns a rejected promise, which is fine — the component's happy path for
// non-Stripe UI (method tabs, country select, labels) is what we're exercising.
vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn(() => Promise.resolve(null)),
}))

describe('CheckoutPaymentBay', () => {
  it('renders the §02 numbered header with PCI-DSS badge', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    expect(wrapper.find('.bay__number').text()).toBe('§ 02')
    expect(wrapper.text()).toContain('PCI-DSS')
  })

  it('renders four payment method tabs with card active', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    const tabs = wrapper.findAll('.method')
    expect(tabs).toHaveLength(4)
    expect(tabs[0].classes()).toContain('method--active')
    expect(tabs[0].text()).toContain('Card')
    expect(tabs[1].text()).toContain('PayPal')
    expect(tabs[2].text()).toContain('Gift Card')
    expect(tabs[3].text()).toContain('Pay on Arrival')
  })

  it('marks non-card tabs as disabled', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    const disabledTabs = wrapper.findAll('.method--disabled')
    expect(disabledTabs).toHaveLength(3)
    expect(disabledTabs[0].attributes('disabled')).toBeDefined()
  })

  it('shows the billing country selector with US default', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    const select = wrapper.find<HTMLSelectElement>('.payment-bay__country')
    expect(select.element.value).toBe('US')
  })

  it('shows the loyalty checkbox for guests and save-card for authenticated users', async () => {
    const guestWrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    expect(guestWrapper.text()).toContain('Final Cut Rewards')

    const authWrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: 'a@b.c', isAuthenticated: true },
    })
    expect(authWrapper.text()).toContain('Save this card')
  })

  it('exposes a submit method via defineExpose', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    // defineExpose makes `submit` available on the component instance.
    expect(typeof (wrapper.vm as unknown as { submit: unknown }).submit).toBe('function')
  })
})
