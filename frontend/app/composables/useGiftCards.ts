import type { GiftCard } from '~/types/gift-card'
import { apiFetch } from '~/utils/api'

interface PurchaseGiftCardData {
  amount: number
  recipientEmail: string
  recipientName: string
  senderName: string
  message: string
  paymentMethodId: string
  idempotencyKey: string
}

export function useGiftCards() {
  const purchase = (data: PurchaseGiftCardData) =>
    apiFetch<{ data: GiftCard }>('/api/gift-cards/purchase', {
      method: 'POST',
      body: {
        amount: data.amount,
        recipient_email: data.recipientEmail,
        recipient_name: data.recipientName,
        sender_name: data.senderName,
        message: data.message,
        payment_method_id: data.paymentMethodId,
      },
      idempotencyKey: data.idempotencyKey,
    })

  const confirm = (paymentIntentId: string) =>
    apiFetch<{ data: GiftCard }>('/api/gift-cards/confirm', {
      method: 'POST',
      body: { paymentIntentId },
    })

  const checkBalance = (code: string) =>
    apiFetch<{ data: { balance: number; status: string } }>('/api/gift-cards/balance', {
      query: { code },
    })

  return { purchase, confirm, checkBalance }
}
