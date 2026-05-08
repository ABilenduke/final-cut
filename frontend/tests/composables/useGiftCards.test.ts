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

  it('purchase sends camelCase payload with new design fields and idempotency key', async () => {
    mockApiFetch.mockResolvedValue({ data: {} })
    const { purchase } = useGiftCards()
    await purchase({
      amount: 5000,
      recipientEmail: 'friend@test.com',
      recipientName: 'Friend',
      senderName: 'Me',
      message: 'Enjoy!',
      edition: 'gold',
      deliveryMethod: 'print',
      scheduledSendAt: '2026-06-01T09:00:00.000Z',
      paymentMethodId: 'pm-1',
      idempotencyKey: 'uuid-abc',
    })
    expect(mockApiFetch).toHaveBeenCalledWith('/api/gift-cards/purchase', {
      method: 'POST',
      body: {
        amount: 5000,
        recipientEmail: 'friend@test.com',
        recipientName: 'Friend',
        senderName: 'Me',
        message: 'Enjoy!',
        edition: 'gold',
        deliveryMethod: 'print',
        scheduledSendAt: '2026-06-01T09:00:00.000Z',
        paymentMethodId: 'pm-1',
      },
      idempotencyKey: 'uuid-abc',
    })
  })

  it('purchase passes scheduledSendAt as null for immediate sends', async () => {
    mockApiFetch.mockResolvedValue({ data: {} })
    const { purchase } = useGiftCards()
    await purchase({
      amount: 2500,
      recipientEmail: 'a@b.test',
      recipientName: 'A',
      senderName: 'B',
      message: null,
      edition: 'reactor',
      deliveryMethod: 'email',
      scheduledSendAt: null,
      paymentMethodId: 'pm-2',
      idempotencyKey: 'uuid-2',
    })
    const call = mockApiFetch.mock.calls[0]
    expect(call[1]?.body).toMatchObject({
      edition: 'reactor',
      deliveryMethod: 'email',
      scheduledSendAt: null,
      message: null,
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

  it('checkBalance sends code as query parameter', async () => {
    mockApiFetch.mockResolvedValue({ data: { balance: 5000, status: 'active' } })
    const { checkBalance } = useGiftCards()
    await checkBalance('GC-XYZ')
    expect(mockApiFetch).toHaveBeenCalledWith('/api/gift-cards/balance', {
      query: { code: 'GC-XYZ' },
    })
  })
})
