import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
import { useGiftCards } from '~/composables/useGiftCards'

const mockApiFetch = vi.mocked(apiFetch)

describe('useGiftCards', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('purchase sends correct payload with idempotency key', async () => {
    mockApiFetch.mockResolvedValue({ data: {} })
    const { purchase } = useGiftCards()
    await purchase({
      amount: 5000,
      recipientEmail: 'friend@test.com',
      recipientName: 'Friend',
      senderName: 'Me',
      message: 'Enjoy!',
      paymentMethodId: 'pm-1',
      idempotencyKey: 'uuid-abc',
    })
    expect(mockApiFetch).toHaveBeenCalledWith('/api/gift-cards/purchase', {
      method: 'POST',
      body: {
        amount: 5000,
        recipient_email: 'friend@test.com',
        recipient_name: 'Friend',
        sender_name: 'Me',
        message: 'Enjoy!',
        payment_method_id: 'pm-1',
      },
      idempotencyKey: 'uuid-abc',
    })
  })

  it('confirm sends paymentIntentId', async () => {
    mockApiFetch.mockResolvedValue({ data: {} })
    const { confirm } = useGiftCards()
    await confirm('pi_123')
    expect(mockApiFetch).toHaveBeenCalledWith('/api/gift-cards/confirm', {
      method: 'POST',
      body: { paymentIntentId: 'pi_123' },
    })
  })

  it('checkBalance sends code in POST body (not query string)', async () => {
    mockApiFetch.mockResolvedValue({ data: { balance: 5000, status: 'active' } })
    const { checkBalance } = useGiftCards()
    await checkBalance('GC-XYZ')
    expect(mockApiFetch).toHaveBeenCalledWith('/api/gift-cards/balance', {
      method: 'POST',
      body: { code: 'GC-XYZ' },
    })
  })
})
