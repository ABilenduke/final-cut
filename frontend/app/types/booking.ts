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
  // UUID — addresses the seat row when posting bookings. Never displayed.
  id: string
  // Human label e.g. "A12". Always use this for any user-visible text.
  label: string
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
