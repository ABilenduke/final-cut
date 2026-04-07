interface WeekRange {
  start: Date   // Monday 00:00:00 of the week (inclusive)
  end: Date     // Next Monday 00:00:00 (exclusive upper bound)
  months: Array<{ month: number; year: number }>  // 1-indexed months
}

/**
 * Compute the Monday-to-Sunday week range containing a given date,
 * plus the month/year pairs needed to fetch data that might span
 * a month boundary.
 *
 * Uses ISO week convention: Monday is the first day of the week.
 * Months in output are 1-indexed (January = 1) to match the backend
 * calendar API.
 *
 * IMPORTANT: This function uses local time. Pass `new Date()` for the
 * current local date. Do NOT pass dates parsed from ISO strings
 * (e.g., `new Date('2026-04-07')`) as those parse as UTC midnight and
 * may shift to the previous day in western timezones.
 */
export function weekRange(today: Date): WeekRange {
  // Clone to avoid mutating the input
  const date = new Date(today)

  // Find Monday of the week containing `today`
  // getDay(): 0 = Sun, 1 = Mon, ... 6 = Sat
  const day = date.getDay()
  const diffToMonday = day === 0 ? 6 : day - 1
  const monday = new Date(date)
  monday.setDate(date.getDate() - diffToMonday)
  monday.setHours(0, 0, 0, 0)

  // End = next Monday 00:00:00 (exclusive upper bound)
  const nextMonday = new Date(monday)
  nextMonday.setDate(monday.getDate() + 7)
  nextMonday.setHours(0, 0, 0, 0)

  // Sunday is the last day of the week (for month-span detection)
  const sunday = new Date(monday)
  sunday.setDate(monday.getDate() + 6)

  // Determine which month(s) the week spans (1-indexed)
  const months: Array<{ month: number; year: number }> = [
    { month: monday.getMonth() + 1, year: monday.getFullYear() },
  ]

  if (
    sunday.getMonth() !== monday.getMonth()
    || sunday.getFullYear() !== monday.getFullYear()
  ) {
    months.push({
      month: sunday.getMonth() + 1,
      year: sunday.getFullYear(),
    })
  }

  return { start: monday, end: nextMonday, months }
}
