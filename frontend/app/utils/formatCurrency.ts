const formatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
})

/**
 * Format cents to a dollar string.
 * e.g. 1299 → "$12.99", 0 → "$0.00"
 */
export function formatCurrency(cents: number): string {
  return formatter.format(cents / 100)
}
