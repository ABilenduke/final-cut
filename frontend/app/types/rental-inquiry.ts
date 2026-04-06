export interface RentalInquiry {
  id: string
  eventType: 'birthday' | 'corporate' | 'proposal' | 'custom'
  preferredDate: string
  guestCount: number
  name: string
  email: string
  phone: string | null
  message: string
  status: 'pending' | 'contacted' | 'confirmed' | 'declined'
  createdAt: string
}
