export interface Booking {
  id: string
  confirmationCode: string
  showtimeId: string
  movieTitle: string
  moviePosterUrl: string
  screenName: string
  startTime: string
  seats: BookingSeat[]
  foodItems: BookingFoodItem[]
  subtotal: number
  discount: number
  total: number
  paymentMethod: 'card' | 'gift_card' | 'mixed'
  userId: string | null
  guestEmail: string | null
  status: 'confirmed' | 'cancelled' | 'refunded'
  createdAt: string
}

export interface BookingSeat {
  seatId: string
  section: string
  price: number
}

export interface BookingFoodItem {
  itemId: string
  name: string
  quantity: number
  unitPrice: number
  totalPrice: number
}
