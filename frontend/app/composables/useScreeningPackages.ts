import { useApiFetch } from '~/utils/api'

/** One private-screening package from GET /api/screening-packages. */
export interface ScreeningPackage {
  id: string
  name: string
  description: string
  startingPrice: number
  features: string[]
}

interface ScreeningPackagesResponse {
  data: ScreeningPackage[]
}

/**
 * SSR-friendly fetch wrapper for the admin-managed private-screening
 * packages (admin-v2 Plan 14 — replaced the hardcoded page array).
 */
export function useScreeningPackages() {
  return useApiFetch<ScreeningPackagesResponse>('/api/screening-packages', {
    key: 'screening-packages',
  })
}
