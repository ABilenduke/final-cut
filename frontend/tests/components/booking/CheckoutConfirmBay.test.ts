import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CheckoutConfirmBay from '~/components/booking/CheckoutConfirmBay.vue'

const baseProps = {
  total: 3700,
  timeRemaining: 462, // 07:42
}

describe('CheckoutConfirmBay', () => {
  it('renders the § 05 numbered header', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, { props: baseProps })
    expect(wrapper.find('.bay__number').text()).toBe('§ 05')
    expect(wrapper.text()).toContain('Confirm')
  })

  it('renders the ticketing terms + auditorium policy consent copy', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, { props: baseProps })
    const text = wrapper.text()
    expect(text).toContain('ticketing terms')
    expect(text).toContain('auditorium policy')
    expect(text).toContain('No late entry after 10 minutes; phones silenced and stowed.')
  })

  it('emits update:acceptTerms when the consent checkbox toggles', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, {
      props: { ...baseProps, acceptTerms: false },
    })
    await wrapper.find<HTMLInputElement>('.confirm-bay__check-box').setValue(true)

    const emitted = wrapper.emitted('update:acceptTerms')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toBe(true)
  })

  it('emits submit and shows the total amount on the pay button', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, { props: baseProps })
    const payBtn = wrapper.find('.confirm-bay__pay')
    expect(payBtn.attributes('disabled')).toBeUndefined()
    expect(payBtn.text()).toContain('Confirm & pay')
    expect(payBtn.find('.confirm-bay__pay-amt').text()).toBe('$37.00')

    await payBtn.trigger('click')
    expect(wrapper.emitted('submit')).toBeDefined()
  })

  it('disables the pay button and shows a spinner label while submitting', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, {
      props: { ...baseProps, submitting: true },
    })
    const payBtn = wrapper.find('.confirm-bay__pay')
    expect(payBtn.attributes('disabled')).toBeDefined()
    expect(payBtn.text()).toContain('Processing…')
  })

  it('disables the pay button when the disabled prop is true', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, {
      props: { ...baseProps, disabled: true },
    })
    expect(wrapper.find('.confirm-bay__pay').attributes('disabled')).toBeDefined()
  })

  it('mirrors the hold timer in the authorization note', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, { props: baseProps })
    expect(wrapper.find('.confirm-bay__note').text()).toContain('07:42')
  })

  it('renders all three trust badges', async () => {
    const wrapper = await mountSuspended(CheckoutConfirmBay, { props: baseProps })
    const trust = wrapper.find('.confirm-bay__trust').text()
    expect(trust).toContain('TLS 1.3')
    expect(trust).toContain('PCI-DSS')
    expect(trust).toContain('3-D Secure')
  })
})
