import type { JobOpeningsResponse } from '~/types/job-opening'
import { useApiFetch } from '~/utils/api'

/**
 * SSR-friendly fetch wrapper for the admin-managed careers openings
 * (admin-v2 Plan 13 — replaced the hardcoded array in the careers page).
 */
export function useJobOpenings() {
  return useApiFetch<JobOpeningsResponse>('/api/job-openings', {
    key: 'job-openings',
  })
}
