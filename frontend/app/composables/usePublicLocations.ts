/**
 * usePublicLocations — SSR-friendly catalog of theater venues.
 *
 * Distinct from `useLocations` (which owns the user's activeLocation
 * preference in localStorage). This composable is purely fetch-and-serve:
 * no localStorage reads or writes, no activeLocation state.
 *
 * Used by:
 *   /locations index page
 *   /locations/:slug detail page
 *   Home page locations strip (Task 4)
 *   Movie detail cross-location distance captions (Task 6)
 */
import type { Location } from '~/types/location'
import { useApiFetch } from '~/utils/api'

interface LocationListResponse {
  data: Location[]
}

interface LocationDetailResponse {
  data: Location
}

export function usePublicLocations() {
  /**
   * Fetch all venues, alphabetical (server returns them sorted by name).
   * Uses a stable `useFetch` key so Nuxt deduplicates concurrent calls
   * on the same SSR render and hydrates correctly on the client.
   */
  function fetchLocations() {
    return useApiFetch<LocationListResponse>('/api/locations', {
      // Explicit key ensures Nuxt dedup + hydration works across pages
      // that might call this simultaneously.
    })
  }

  /**
   * Fetch a single venue by slug.
   * Returns null-safe: if the API responds 404, `data.value` will be null
   * and `error.value` will carry the response. Callers should check for
   * error and render a 404 page accordingly.
   */
  function fetchBySlug(slug: string) {
    return useApiFetch<LocationDetailResponse>(`/api/locations/${slug}`)
  }

  return { fetchLocations, fetchBySlug }
}
