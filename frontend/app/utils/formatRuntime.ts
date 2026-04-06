/**
 * Format minutes to human-readable runtime.
 * e.g. 154 → "2h 34m", 45 → "45m", 60 → "1h 0m"
 */
export function formatRuntime(minutes: number): string {
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60

  if (hours === 0) return `${mins}m`
  return `${hours}h ${mins}m`
}
