import type { FeaturedSlidesResponse } from '~/types/featured-slide'
import { useApiFetch } from '~/utils/api'

/**
 * SSR-friendly fetch wrapper for the admin-curated featured slides.
 * Returns the useFetch tuple — data, pending, error, refresh.
 *
 * The explicit key 'featured-slides' prevents duplicate fetches when called
 * from multiple components on the same SSR render.
 *
 * The endpoint returns only currently-active, in-window slides ordered
 * by display_order — no client-side filtering needed.
 */
export function useFeaturedSlides() {
  return useApiFetch<FeaturedSlidesResponse>('/api/featured-slides', {
    key: 'featured-slides',
  })
}
