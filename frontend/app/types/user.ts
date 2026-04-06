export interface User {
  id: string
  email: string
  name: string
  avatarUrl: string | null
  loyaltyPoints: number
  loyaltyTier: 'member' | 'premier'
  premierExpiry: string | null
  createdAt: string
}

export interface UserProfile extends User {
  phone: string | null
  dateOfBirth: string | null
}
