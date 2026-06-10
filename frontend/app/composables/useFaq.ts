import type { FaqResponse } from '~/types/faq'
import { useApiFetch } from '~/utils/api'

/**
 * SSR-friendly fetch wrapper for the admin-managed FAQ
 * (admin-v2 Plan 13 — replaced the static ~/data/faq.ts).
 * Items arrive pre-grouped by category in display order.
 */
export function useFaq() {
  return useApiFetch<FaqResponse>('/api/faq', {
    key: 'faq',
  })
}
