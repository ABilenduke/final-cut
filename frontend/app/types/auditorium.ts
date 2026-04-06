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
  id: string
  row: string
  number: number
  status: 'available' | 'taken' | 'held'
  type: 'standard' | 'premium' | 'accessible'
  price: number
}
