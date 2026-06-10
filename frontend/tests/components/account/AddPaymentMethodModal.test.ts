import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'

mockNuxtImport('useRuntimeConfig', () => {
  return () => ({ public: { stripePublishableKey: 'pk_test_account' } })
})

const fakeCardElement = { mount: vi.fn(), destroy: vi.fn(), on: vi.fn() }
const fakeStripe = {
  elements: vi.fn(() => ({ create: vi.fn(() => fakeCardElement) })),
  confirmCardSetup: vi.fn(),
}

vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn(() => Promise.resolve(fakeStripe)),
}))

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
import AddPaymentMethodModal from '~/components/account/AddPaymentMethodModal.vue'

const mockApiFetch = vi.mocked(apiFetch)

function inBody(selector: string): HTMLElement | null {
  return document.body.querySelector(selector)
}

async function clickSave(): Promise<void> {
  await vi.waitFor(() => {
    const button = inBody('[data-testid="pm-add-submit"]') as HTMLButtonElement | null
    expect(button).not.toBeNull()
    expect(button!.disabled).toBe(false)
  })
  inBody('[data-testid="pm-add-submit"]')!.dispatchEvent(new MouseEvent('click', { bubbles: true }))
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  mockApiFetch.mockResolvedValue({ data: { clientSecret: 'seti_secret_123' } })
  fakeStripe.confirmCardSetup.mockResolvedValue({ setupIntent: { status: 'succeeded' } })
})

describe('AddPaymentMethodModal', () => {
  it('creates a SetupIntent, confirms the card, and emits added', async () => {
    const wrapper = await mountSuspended(AddPaymentMethodModal)
    await clickSave()
    await vi.waitFor(() => expect(wrapper.emitted('added')).toBeTruthy())

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/api/account/payment-methods',
      expect.objectContaining({ method: 'POST' }),
    )
    expect(fakeStripe.confirmCardSetup).toHaveBeenCalledWith(
      'seti_secret_123',
      expect.objectContaining({ payment_method: { card: fakeCardElement } }),
    )
  })

  it('shows the Stripe error and does not emit added when confirmation fails', async () => {
    fakeStripe.confirmCardSetup.mockResolvedValue({ error: { message: 'Your card was declined.' } })

    const wrapper = await mountSuspended(AddPaymentMethodModal)
    await clickSave()
    await vi.waitFor(() => expect(inBody('[data-testid="pm-add-error"]')).not.toBeNull())

    expect(inBody('[data-testid="pm-add-error"]')!.textContent).toContain('declined')
    expect(wrapper.emitted('added')).toBeFalsy()
  })
})
