export interface Auditorium {
  id: string
  name: string
  rows: AuditoriumRow[]
  seatsPerRow: number
  totalSeats: number
  sections: AuditoriumSection[]
}

export interface AuditoriumRow {
  label: string
  seats: Seat[]
  section: string
}

export interface AuditoriumSection {
  name: string
  priceMultiplier: number
}

export interface Seat {
  // UUID — addresses the seat row when posting bookings. Never displayed.
  id: string
  // Human label e.g. "A12". Always use this for any user-visible text.
  label: string
  row: string
  number: number
  status: 'available' | 'taken' | 'held'
  type: 'standard' | 'premium' | 'accessible'
  price: number
}
