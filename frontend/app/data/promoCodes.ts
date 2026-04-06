/**
 * Client-side promo code preview config.
 * Mirrors backend/config/promo_codes.php for instant discount preview.
 * This is NON-AUTHORITATIVE — the server validates at booking time.
 */
export interface PromoCodeConfig {
  type: 'percentage' | 'fixed'
  value: number
  maxDiscount?: number
}

export const promoCodes: Record<string, PromoCodeConfig> = {
  SAVE10: {
    type: 'percentage',
    value: 10,
    maxDiscount: 2000,
  },
  WELCOME5: {
    type: 'fixed',
    value: 500,
  },
}
