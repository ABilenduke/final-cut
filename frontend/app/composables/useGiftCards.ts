import type { GiftCard, PurchaseGiftCardData } from '~/types/gift-card'
import { apiFetch } from '~/utils/api'

export function useGiftCards() {
  const purchase = (data: PurchaseGiftCardData) =>
    apiFetch<{ data: GiftCard }>('/api/gift-cards/purchase', {
      method: 'POST',
      body: {
        amount: data.amount,
        recipientEmail: data.recipientEmail,
        recipientName: data.recipientName,
        senderName: data.senderName,
        message: data.message,
        edition: data.edition,
        deliveryMethod: data.deliveryMethod,
        scheduledSendAt: data.scheduledSendAt,
        paymentMethodId: data.paymentMethodId,
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
