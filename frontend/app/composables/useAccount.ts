import type { UserProfile } from '~/types/user'
import type { Booking } from '~/types/booking'
import { apiFetch, useApiFetch } from '~/utils/api'

export function useAccount() {
  const profile = () =>
    useApiFetch<{ data: UserProfile }>('/api/account/profile')

  const updateProfile = (data: Partial<UserProfile>) =>
    apiFetch<{ data: UserProfile }>('/api/account/profile', {
      method: 'PATCH',
      body: data as Record<string, any>,
    })

  const orders = (page?: number, limit?: number) =>
    useApiFetch<{ data: Booking[]; meta: { total: number; page: number; per_page: number } }>(
      '/api/account/orders',
      { query: { page, limit } },
    )

  const bookings = () =>
    useApiFetch<{ data: Booking[] }>('/api/account/bookings', {
      query: { upcoming: true },
    })

  const loyalty = () =>
    useApiFetch<{ data: { points: number; tier: string; premierExpiry: string | null; history: unknown[] } }>(
      '/api/account/loyalty',
    )

  const paymentMethods = () =>
    useApiFetch<{ data: Array<{ id: string; brand: string; last4: string; expMonth: number; expYear: number }> }>(
      '/api/account/payment-methods',
    )

  const addPaymentMethod = () =>
    apiFetch<{ data: { clientSecret: string } }>('/api/account/payment-methods', {
      method: 'POST',
    })

  const removePaymentMethod = (id: string) =>
    apiFetch<{ data: { success: true } }>(`/api/account/payment-methods/${id}`, {
      method: 'DELETE',
    })

  return {
    profile, updateProfile, orders, bookings,
    loyalty, paymentMethods, addPaymentMethod, removePaymentMethod,
  }
}
