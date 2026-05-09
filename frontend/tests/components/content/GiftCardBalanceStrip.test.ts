import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
import GiftCardBalanceStrip from '~/components/content/GiftCardBalanceStrip.vue'

const mockApiFetch = vi.mocked(apiFetch)

describe('GiftCardBalanceStrip', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('renders an input and lookup button', async () => {
    const wrapper = await mountSuspended(GiftCardBalanceStrip)
    expect(wrapper.find('.gift-card-balance-strip__input').exists()).toBe(true)
    expect(wrapper.find('.gift-card-balance-strip__btn').text()).toBe('Look up →')
  })

  it('shows formatted balance result on successful lookup', async () => {
    mockApiFetch.mockResolvedValue({ data: { balance: 8750, status: 'active' } })
    const wrapper = await mountSuspended(GiftCardBalanceStrip)
    await wrapper.find('.gift-card-balance-strip__input').setValue('SEED-ACTIVE-5000')
    await wrapper.find('form').trigger('submit')
    await new Promise((r) => setTimeout(r, 0))
    expect(wrapper.text()).toContain('Balance · $87.50 · Active')
  })

  it('shows error state when lookup fails', async () => {
    mockApiFetch.mockRejectedValue({ errors: [{ message: 'Gift card not found.' }], status: 404 })
    const wrapper = await mountSuspended(GiftCardBalanceStrip)
    await wrapper.find('.gift-card-balance-strip__input').setValue('NOPE')
    await wrapper.find('form').trigger('submit')
    await new Promise((r) => setTimeout(r, 0))
    expect(wrapper.text()).toContain('Gift card not found.')
    expect(wrapper.find('.gift-card-balance-strip__result--error').exists()).toBe(true)
  })

  it('clears any prior result when the input is empty on submit', async () => {
    mockApiFetch.mockResolvedValue({ data: { balance: 5000, status: 'active' } })
    const wrapper = await mountSuspended(GiftCardBalanceStrip)
    await wrapper.find('.gift-card-balance-strip__input').setValue('CODE')
    await wrapper.find('form').trigger('submit')
    await new Promise((r) => setTimeout(r, 0))
    expect(wrapper.text()).toContain('Balance · $50.00')
    await wrapper.find('.gift-card-balance-strip__input').setValue('')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.find('.gift-card-balance-strip__result').exists()).toBe(false)
  })
})
