export type GiftCardEdition = 'reactor' | 'gold' | 'void'
export type GiftCardDeliveryMethod = 'email' | 'print'
export type GiftCardStatus = 'active' | 'depleted' | 'expired' | 'voided'

export interface GiftCard {
  id: string
  code: string
  initialBalance: number
  currentBalance: number
  recipientEmail: string
  recipientName: string
  senderName: string
  message: string | null
  edition: GiftCardEdition
  deliveryMethod: GiftCardDeliveryMethod
  scheduledSendAt: string | null
  purchasedAt: string | null
  status: GiftCardStatus
}

export interface PurchaseGiftCardData {
  amount: number
  recipientEmail: string
  recipientName: string
  senderName: string
  message: string | null
  edition: GiftCardEdition
  deliveryMethod: GiftCardDeliveryMethod
  /** ISO-8601 timestamp; null sends immediately. */
  scheduledSendAt: string | null
  paymentMethodId: string
  idempotencyKey: string
}
