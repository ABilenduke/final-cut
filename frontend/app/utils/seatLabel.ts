/**
 * Format a seat ID for display.
 * e.g. "A1" → "Row A, Seat 1", "B12" → "Row B, Seat 12"
 */
export function seatLabel(seatId: string): string {
  const match = seatId.match(/^([A-Z]+)(\d+)$/i)
  if (!match) return seatId

  const row = match[1]!.toUpperCase()
  const number = match[2]!
  return `Row ${row}, Seat ${number}`
}
