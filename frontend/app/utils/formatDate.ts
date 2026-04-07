const dateFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
})

const dateTimeFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
})

/**
 * Parse a date string into a Date object.
 * Date-only strings (YYYY-MM-DD) are anchored to UTC noon to avoid
 * day-shift issues in timezones west of UTC (up to UTC-12).
 */
function parseDate(iso: string): Date {
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    return new Date(`${iso}T12:00:00Z`)
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

const timeFormatter = new Intl.DateTimeFormat('en-US', {
  hour: 'numeric',
  minute: '2-digit',
})

/**
 * Format ISO datetime to time-only string.
 * e.g. "2026-04-03T19:00:00Z" → "7:00 PM"
 */
export function formatTime(iso: string): string {
  return timeFormatter.format(new Date(iso))
}

const weekdayDateFormatter = new Intl.DateTimeFormat('en-US', {
  weekday: 'short',
  month: 'short',
  day: 'numeric',
})

/**
 * Format ISO date to weekday + date string.
 * e.g. "2026-04-09" → "Thu, Apr 9"
 */
export function formatWeekdayDate(iso: string): string {
  return weekdayDateFormatter.format(parseDate(iso))
}
