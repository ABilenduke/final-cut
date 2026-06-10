import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'

// Separate file from CheckoutPaymentBay.test.ts: that one stubs loadStripe
// to null (UI-only assertions); this one drives a full submit through a
// working fake to pin the emitted payload shape.
mockNuxtImport('useRuntimeConfig', () => {
  return () => ({ public: { stripePublishableKey: 'pk_test_submit' } })
})

const fakeCardElement = { mount: vi.fn(), destroy: vi.fn(), on: vi.fn() }
const fakeStripe = {
  elements: vi.fn(() => ({ create: vi.fn(() => fakeCardElement) })),
  createPaymentMethod: vi.fn(),
}

vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn(() => Promise.resolve(fakeStripe)),
}))

import CheckoutPaymentBay from '~/components/booking/CheckoutPaymentBay.vue'

beforeEach(() => {
  vi.clearAllMocks()
  fakeStripe.createPaymentMethod.mockResolvedValue({ paymentMethod: { id: 'pm_submit_1' } })
})

describe('CheckoutPaymentBay submit payload', () => {
  it('includes saveCard for authenticated users who left the box ticked', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: 'member@example.com', isAuthenticated: true },
    })

    await vi.waitFor(() => {
      expect(fakeCardElement.mount).toHaveBeenCalled()
    })
    await (wrapper.vm as unknown as { submit: () => Promise<void> }).submit()

    const payload = wrapper.emitted('submit')![0]![0] as Record<string, unknown>
    expect(payload.paymentMethodId).toBe('pm_submit_1')
    expect(payload.saveCard).toBe(true)
  })

  it('never includes saveCard for guests', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: 'guest@example.com', isAuthenticated: false },
    })

    await vi.waitFor(() => {
      expect(fakeCardElement.mount).toHaveBeenCalled()
    })
    await (wrapper.vm as unknown as { submit: () => Promise<void> }).submit()

    const payload = wrapper.emitted('submit')![0]![0] as Record<string, unknown>
    expect(payload.saveCard).toBeUndefined()
  })
})
