/**
 * Location type — mirrors the backend LocationResource wire contract.
 *
 * The `address` field is a backward-compat flattened string kept for
 * existing consumers. New code should use the structured address fields.
 *
 * `hours` keys are lowercase English weekday names (monday … sunday).
 * A null value means the venue is closed on that day.
 */

export type WeekdayKey =
  | 'monday'
  | 'tuesday'
  | 'wednesday'
  | 'thursday'
  | 'friday'
  | 'saturday'
  | 'sunday'

export interface LocationHours {
  open: string   // e.g. "09:00"
  close: string  // e.g. "23:00"
}

export interface Location {
  id: string
  slug: string
  name: string
  // Structured address fields (Plan 05 / location hierarchy)
  street: string
  city: string
  state: string
  postal_code: string
  country: string        // ISO-2, default "US"
  // Contact
  phone: string | null
  email: string | null
  // Geo coordinates (nullable — some venues may not have them set)
  latitude: number | null
  longitude: number | null
  // IANA timezone
  timezone: string
  // Hours of operation — keyed by lowercased weekday, null = closed
  hours: Partial<Record<WeekdayKey, LocationHours | null>>
  // Backward-compat collapsed address string (returned by LocationResource)
  address: string
  // Optional venue photo URL — may be null until admin uploads one
  imageUrl?: string | null
}
