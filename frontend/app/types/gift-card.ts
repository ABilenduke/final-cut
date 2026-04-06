export interface GiftCard {
  id: string
  code: string
  initialBalance: number
  currentBalance: number
  recipientEmail: string
  recipientName: string
  senderName: string
  message: string
  purchasedAt: string
  status: 'active' | 'depleted' | 'expired'
}
