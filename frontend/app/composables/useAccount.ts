import type { UserProfile } from '~/types/user'
import type { Booking } from '~/types/booking'
import { apiFetch, useApiFetch } from '~/utils/api'

export interface LoyaltyHistoryEntry {
  description: string
  points: number
  date: string
  bookingId: string
}

export interface LoyaltyData {
  points: number
  tier: 'member' | 'premier'
  premierExpiry: string | null
  history: LoyaltyHistoryEntry[]
}

export function useAccount() {
  const profile = () =>
    useApiFetch<{ data: UserProfile }>('/api/account/profile')

  const updateProfile = (data: Record<string, unknown>) =>
    apiFetch<{ data: UserProfile }>('/api/account/profile', {
      method: 'PATCH',
      body: data,
    })

  const orders = (page?: number, perPage?: number) =>
    useApiFetch<{ data: Booking[]; meta: { total: number; page: number; per_page: number } }>(
      '/api/account/orders',
      { query: { page, per_page: perPage } },
    )

  const bookings = () =>
    useApiFetch<{ data: Booking[] }>('/api/account/bookings', {
      query: { upcoming: true },
    })

  const loyalty = () =>
    useApiFetch<{ data: LoyaltyData }>(
      '/api/account/loyalty',
    )

  const paymentMethods = () =>
    useApiFetch<{ data: Array<{ id: string; brand: string; last4: string; expMonth: number; expYear: number }> }>(
      '/api/account/payment-methods',
    )

  const removePaymentMethod = (id: string) =>
    apiFetch<{ data: { success: true } }>(`/api/account/payment-methods/${id}`, {
      method: 'DELETE',
    })

  return {
    profile, updateProfile, orders, bookings,
    loyalty, paymentMethods, removePaymentMethod,
  }
}
