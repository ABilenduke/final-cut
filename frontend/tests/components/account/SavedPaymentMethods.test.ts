import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import SavedPaymentMethods from '~/components/account/SavedPaymentMethods.vue'

function mockMethod(overrides = {}) {
  return {
    id: 'pm_1',
    brand: 'visa',
    last4: '4242',
    expMonth: 12,
    expYear: 2027,
    ...overrides,
  }
}

describe('SavedPaymentMethods', () => {
  it('shows empty state when no methods', async () => {
    const wrapper = await mountSuspended(SavedPaymentMethods, {
      props: { methods: [] },
    })
    expect(wrapper.find('.payment-methods__empty').exists()).toBe(true)
    expect(wrapper.text()).toContain('No saved payment methods')
  })

  it('renders each payment method', async () => {
    const methods = [
      mockMethod({ id: 'pm_1' }),
      mockMethod({ id: 'pm_2', brand: 'mastercard', last4: '5555' }),
    ]
    const wrapper = await mountSuspended(SavedPaymentMethods, {
      props: { methods },
    })
    const items = wrapper.findAll('.payment-methods__item')
    expect(items).toHaveLength(2)
  })

  it('shows brand and last4 digits', async () => {
    const wrapper = await mountSuspended(SavedPaymentMethods, {
      props: { methods: [mockMethod()] },
    })
    const item = wrapper.find('.payment-methods__item')
    expect(item.find('.payment-methods__brand').text()).toBe('Visa')
    expect(item.find('.payment-methods__number').text()).toContain('4242')
  })

  it('emits remove with method id on remove click', async () => {
    const wrapper = await mountSuspended(SavedPaymentMethods, {
      props: { methods: [mockMethod({ id: 'pm_42' })] },
    })
    const removeButton = wrapper.findAll('button').find(b => b.text().includes('Remove'))
    await removeButton!.trigger('click')

    const emitted = wrapper.emitted('remove')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toBe('pm_42')
  })

  it('emits add on add button click', async () => {
    const wrapper = await mountSuspended(SavedPaymentMethods, {
      props: { methods: [] },
    })
    const addButton = wrapper.findAll('button').find(b => b.text().includes('Add Payment Method'))
    await addButton!.trigger('click')

    const emitted = wrapper.emitted('add')
    expect(emitted).toBeDefined()
  })
})
