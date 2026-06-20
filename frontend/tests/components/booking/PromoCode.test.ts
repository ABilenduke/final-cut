import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import PromoCode from '~/components/booking/PromoCode.vue'

describe('PromoCode', () => {
  it('renders the §04 promo bay header', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: null },
    })
    expect(wrapper.find('.bay__number').text()).toBe('§ 04')
    expect(wrapper.text()).toContain('Promo')
    expect(wrapper.find('.promo-bay__applied').exists()).toBe(false)
  })

  it('shows applied chip with remove link when code is set', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: 'SAVE10' },
    })
    const applied = wrapper.find('.promo-bay__applied')
    expect(applied.exists()).toBe(true)
    expect(applied.text()).toContain('SAVE10')
    expect(applied.find('.promo-bay__remove').exists()).toBe(true)
  })

  it('emits apply with trimmed code on button click', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: null },
    })
    const input = wrapper.find<HTMLInputElement>('.promo-bay__input')
    await input.setValue('  DISCOUNT20  ')
    await wrapper.find('.promo-bay__apply').trigger('click')

    const emitted = wrapper.emitted('apply')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toBe('DISCOUNT20')
  })

  it('emits remove on remove click', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: 'SAVE10' },
    })
    await wrapper.find('.promo-bay__remove').trigger('click')

    expect(wrapper.emitted('remove')).toBeDefined()
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('shows an error when applying empty code', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: null },
    })
    await wrapper.find('.promo-bay__apply').trigger('click')

    expect(wrapper.emitted('apply')).toBeUndefined()
    expect(wrapper.find('.promo-bay__error').text()).toBe('Please enter a promo code')
  })

  // The terms-consent checkbox moved to CheckoutConfirmBay; the promo bay is
  // now promo-only. Consent behavior is covered in CheckoutConfirmBay.test.ts.
  it('no longer renders the terms checkbox', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: null },
    })
    expect(wrapper.find('.promo-bay__check-box').exists()).toBe(false)
  })
})
