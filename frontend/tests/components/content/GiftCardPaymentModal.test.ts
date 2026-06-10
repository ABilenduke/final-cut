import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'

// Mocks must precede the SFC import (top-level useRuntimeConfig + loadStripe).
mockNuxtImport('useRuntimeConfig', () => {
  return () => ({ public: { stripePublishableKey: 'pk_test_gift_cards' } })
})

const fakeCardElement = {
  mount: vi.fn(),
  destroy: vi.fn(),
  on: vi.fn(),
}
const fakeStripe = {
  elements: vi.fn(() => ({ create: vi.fn(() => fakeCardElement) })),
  createPaymentMethod: vi.fn(),
  handleCardAction: vi.fn(),
}

vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn(() => Promise.resolve(fakeStripe)),
}))

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
import GiftCardPaymentModal from '~/components/content/GiftCardPaymentModal.vue'
import type { PurchaseGiftCardData } from '~/types/gift-card'

const mockApiFetch = vi.mocked(apiFetch)

const PAYLOAD: Omit<PurchaseGiftCardData, 'paymentMethodId' | 'idempotencyKey'> = {
  amount: 5000,
  recipientEmail: 'friend@example.com',
  recipientName: 'Margot Renard',
  senderName: 'Henri',
  message: 'Enjoy the show.',
  edition: 'gold',
  deliveryMethod: 'email',
  scheduledSendAt: null,
}

const GIFT_CARD = { id: 'gc-1', code: 'GC-ABCD1234', currentBalance: 5000 }

function mountModal() {
  return mountSuspended(GiftCardPaymentModal, {
    props: { payload: PAYLOAD, amountLabel: '$50.00' },
  })
}

// CvModal teleports to <body> — query the document, not the wrapper.
function inBody(selector: string): HTMLElement | null {
  return document.body.querySelector(selector)
}

function clickPay(): void {
  inBody('[data-testid="gc-pay-submit"]')!.dispatchEvent(
    new MouseEvent('click', { bubbles: true }),
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  fakeStripe.createPaymentMethod.mockResolvedValue({ paymentMethod: { id: 'pm_123' } })
  fakeStripe.handleCardAction.mockResolvedValue({})
})

describe('GiftCardPaymentModal', () => {
  it('purchases with the created payment method and emits purchased', async () => {
    mockApiFetch.mockResolvedValue({ data: GIFT_CARD })

    const wrapper = await mountModal()
    clickPay()
    await vi.waitFor(() => expect(wrapper.emitted('purchased')).toBeTruthy())

    expect(fakeStripe.createPaymentMethod).toHaveBeenCalledWith(
      expect.objectContaining({ type: 'card', card: fakeCardElement }),
    )
    expect(mockApiFetch).toHaveBeenCalledWith(
      '/api/gift-cards/purchase',
      expect.objectContaining({
        method: 'POST',
        body: expect.objectContaining({ amount: 5000, paymentMethodId: 'pm_123' }),
        idempotencyKey: expect.stringMatching(/^[0-9a-f-]{36}$/),
      }),
    )
    expect(wrapper.emitted('purchased')![0]![0]).toEqual(GIFT_CARD)
  })

  it('runs the 3DS flow then confirms when the purchase requires action', async () => {
    mockApiFetch
      .mockResolvedValueOnce({
        data: { requiresAction: true, clientSecret: 'cs_test', paymentIntentId: 'pi_test' },
      })
      .mockResolvedValueOnce({ data: GIFT_CARD })

    const wrapper = await mountModal()
    clickPay()
    await vi.waitFor(() => expect(wrapper.emitted('purchased')).toBeTruthy())

    expect(fakeStripe.handleCardAction).toHaveBeenCalledWith('cs_test')
    expect(mockApiFetch).toHaveBeenLastCalledWith(
      '/api/gift-cards/confirm',
      expect.objectContaining({ body: { paymentIntentId: 'pi_test' } }),
    )
  })

  it('shows a decline message and does not emit purchased on 402', async () => {
    mockApiFetch.mockRejectedValue({ status: 402, errors: [{ message: 'Card declined.' }] })

    const wrapper = await mountModal()
    clickPay()
    await vi.waitFor(() =>
      expect(inBody('[data-testid="gc-pay-error"]')).not.toBeNull(),
    )

    expect(inBody('[data-testid="gc-pay-error"]')!.textContent).toContain('declined')
    expect(wrapper.emitted('purchased')).toBeFalsy()
  })
})
