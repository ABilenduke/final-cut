import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch, apiFetch } from '~/utils/api'
import { useAccount } from '~/composables/useAccount'

const mockUseApiFetch = vi.mocked(useApiFetch)
const mockApiFetch = vi.mocked(apiFetch)

describe('useAccount', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('profile fetches from /api/account/profile', () => {
    const { profile } = useAccount()
    profile()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/account/profile')
  })

  it('updateProfile sends PATCH', async () => {
    mockApiFetch.mockResolvedValue({ data: {} })
    const { updateProfile } = useAccount()
    await updateProfile({ name: 'New Name' })
    expect(mockApiFetch).toHaveBeenCalledWith('/api/account/profile', {
      method: 'PATCH',
      body: { name: 'New Name' },
    })
  })

  it('orders passes pagination params', () => {
    const { orders } = useAccount()
    orders(2, 10)
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/account/orders', {
      query: { page: 2, per_page: 10 },
    })
  })

  it('orders works without params', () => {
    const { orders } = useAccount()
    orders()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/account/orders', {
      query: { page: undefined, per_page: undefined },
    })
  })

  it('bookings fetches upcoming', () => {
    const { bookings } = useAccount()
    bookings()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/account/bookings', {
      query: { upcoming: true },
    })
  })

  it('loyalty fetches from /api/account/loyalty', () => {
    const { loyalty } = useAccount()
    loyalty()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/account/loyalty')
  })

  it('paymentMethods fetches from /api/account/payment-methods', () => {
    const { paymentMethods } = useAccount()
    paymentMethods()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/account/payment-methods')
  })

  it('addPaymentMethod sends POST', async () => {
    mockApiFetch.mockResolvedValue({ data: { clientSecret: 'cs_123' } })
    const { addPaymentMethod } = useAccount()
    await addPaymentMethod()
    expect(mockApiFetch).toHaveBeenCalledWith('/api/account/payment-methods', {
      method: 'POST',
    })
  })

  it('removePaymentMethod sends DELETE', async () => {
    mockApiFetch.mockResolvedValue({ data: { success: true } })
    const { removePaymentMethod } = useAccount()
    await removePaymentMethod('pm-123')
    expect(mockApiFetch).toHaveBeenCalledWith('/api/account/payment-methods/pm-123', {
      method: 'DELETE',
    })
  })
})
