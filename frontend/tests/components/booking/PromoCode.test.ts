import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import PromoCode from '~/components/booking/PromoCode.vue'

describe('PromoCode', () => {
  it('shows input form when no code applied', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: null },
    })
    expect(wrapper.find('.promo-code__form').exists()).toBe(true)
    expect(wrapper.find('.promo-code__applied').exists()).toBe(false)
  })

  it('shows applied code with remove button when code is set', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: 'SAVE10' },
    })
    expect(wrapper.find('.promo-code__form').exists()).toBe(false)
    expect(wrapper.find('.promo-code__applied').exists()).toBe(true)
    expect(wrapper.find('.promo-code__code').text()).toBe('SAVE10')
    expect(wrapper.find('.promo-code__remove').exists()).toBe(true)
  })

  it('emits apply with code on button click', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: null },
    })
    // Find the CvInput and set its value
    const input = wrapper.findComponent({ name: 'CvInput' })
    // Simulate v-model update
    input.vm.$emit('update:modelValue', 'DISCOUNT20')
    await wrapper.vm.$nextTick()

    // Click apply button
    const applyButton = wrapper.findComponent({ name: 'CvButton' })
    await applyButton.trigger('click')

    const emitted = wrapper.emitted('apply')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toBe('DISCOUNT20')
  })

  it('emits remove on remove click', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: 'SAVE10' },
    })
    await wrapper.find('.promo-code__remove').trigger('click')

    expect(wrapper.emitted('remove')).toBeDefined()
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('shows error when applying empty code', async () => {
    const wrapper = await mountSuspended(PromoCode, {
      props: { appliedCode: null },
    })
    // Click apply without entering a code
    const applyButton = wrapper.findComponent({ name: 'CvButton' })
    await applyButton.trigger('click')

    // Should NOT emit apply
    expect(wrapper.emitted('apply')).toBeUndefined()

    // Should pass error to CvInput
    const input = wrapper.findComponent({ name: 'CvInput' })
    expect(input.props('error')).toBe('Please enter a promo code')
  })
})
