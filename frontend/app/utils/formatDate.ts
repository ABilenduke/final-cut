/** Fixed timezone for deterministic SSR/client rendering. */
const TIMEZONE = 'America/New_York'

const dateFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
  timeZone: TIMEZONE,
})

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
  timeZone: TIMEZONE,
})

/**
 * Parse a date string into a Date object.
 * Date-only strings (YYYY-MM-DD) are treated as local calendar dates
 * by appending T12:00:00 to avoid UTC-midnight timezone shift.
 */
function parseDate(iso: string): Date {
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    return new Date(`${iso}T12:00:00`)
  }
  return new Date(iso)
}

/**
 * Format ISO string to readable date.
 * e.g. "2026-04-03" → "Apr 3, 2026"
 */
export function formatDate(iso: string): string {
  return dateFormatter.format(parseDate(iso))
}

/**
 * Format ISO string to readable date + time.
 * e.g. "2026-04-03T19:00:00" → "Apr 3, 2026, 7:00 PM"
 */
export function formatDateTime(iso: string): string {
  return dateTimeFormatter.format(parseDate(iso))
}
