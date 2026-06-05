const formatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
})

/**
 * Format cents to a dollar string.
 * e.g. 1299 → "$12.99", 0 → "$0.00". Negatives pass through deliberately
 * (-500 → "-$5.00") so discount/refund line items keep their leading minus;
 * clamp upstream (or use formatCurrencyParts) where a minus must never show.
 */
export function formatCurrency(cents: number): string {
  return formatter.format(cents / 100)
}

/**
 * Split cents into the parts UI surfaces typically render in different type
 * styles — a whole number with thousands separators and a small decimal tail.
 * Used by the gift-card visual and order-summary total where the decimal is
 * typeset smaller than the dollar amount. (The "$" mark lives in the template,
 * not here — this returns only { whole, dec }.) Clamps negatives to 0; its
 * consumers never display a minus.
 */
export function formatCurrencyParts(cents: number): { whole: string; dec: string } {
  const safe = Math.max(0, cents)
  return {
    whole: Math.floor(safe / 100).toLocaleString('en-US'),
    dec: String(safe % 100).padStart(2, '0'),
  }
}
