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

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
import CheckoutPaymentBay from '~/components/booking/CheckoutPaymentBay.vue'

const mockApiFetch = vi.mocked(apiFetch)

const SAVED_CARDS = [
  { id: 'pm_saved_1', brand: 'visa', last4: '4242', expMonth: 12, expYear: 2027 },
  { id: 'pm_saved_2', brand: 'amex', last4: '0005', expMonth: 3, expYear: 2028 },
]

beforeEach(() => {
  vi.clearAllMocks()
  fakeStripe.createPaymentMethod.mockResolvedValue({ paymentMethod: { id: 'pm_submit_1' } })
  // Saved-card lookup (admin-v5 Plan 03): empty by default.
  mockApiFetch.mockResolvedValue({ data: [] })
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

  it('pays with a selected saved card without touching Elements', async () => {
    mockApiFetch.mockResolvedValue({ data: SAVED_CARDS })

    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: 'member@example.com', isAuthenticated: true },
    })
    await vi.waitFor(() => {
      expect(wrapper.text()).toContain('4242')
    })

    // The first saved card is preselected; submit pays with it directly.
    await (wrapper.vm as unknown as { submit: () => Promise<void> }).submit()

    const payload = wrapper.emitted('submit')![0]![0] as Record<string, unknown>
    expect(payload.paymentMethodId).toBe('pm_saved_1')
    expect(payload.usingSavedCard).toBe(true)
    expect(payload.saveCard).toBeUndefined()
    expect(fakeStripe.createPaymentMethod).not.toHaveBeenCalled()
  })

  it('falls back to the card element when "use a different card" is chosen', async () => {
    mockApiFetch.mockResolvedValue({ data: SAVED_CARDS })

    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: 'member@example.com', isAuthenticated: true },
    })
    await vi.waitFor(() => {
      expect(wrapper.text()).toContain('4242')
    })

    await wrapper.find('[data-testid="pay-new-card"]').trigger('click')
    await vi.waitFor(() => {
      expect(fakeCardElement.mount).toHaveBeenCalled()
    })
    await (wrapper.vm as unknown as { submit: () => Promise<void> }).submit()

    const payload = wrapper.emitted('submit')![0]![0] as Record<string, unknown>
    expect(payload.paymentMethodId).toBe('pm_submit_1')
    expect(payload.usingSavedCard).toBeUndefined()
  })

  it('guests never trigger the saved-card lookup', async () => {
    await mountSuspended(CheckoutPaymentBay, {
      props: { email: 'guest@example.com', isAuthenticated: false },
    })

    expect(mockApiFetch).not.toHaveBeenCalled()
  })
})