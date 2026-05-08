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

/**
 * Split cents into the parts UI surfaces typically render in different type
 * styles — a small currency mark, a large whole number with thousands
 * separators, and a small decimal tail. Used by the gift-card visual and
 * order-summary total where currency / decimal are typeset smaller than the
 * dollar amount.
 */
export function formatCurrencyParts(cents: number): { whole: string; dec: string } {
  const safe = Math.max(0, cents)
  return {
    whole: Math.floor(safe / 100).toLocaleString('en-US'),
    dec: String(safe % 100).padStart(2, '0'),
  }
}
