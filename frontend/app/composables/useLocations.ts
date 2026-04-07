import type { Location } from '~/types/location'
import { apiFetch } from '~/utils/api'

export function useLocations() {
  const locations = useState<Location[]>('locations', () => [])
  const activeLocation = useState<Location | null>('active-location', () => null)

  function setLocation(slug: string) {
    const match = locations.value.find((l) => l.slug === slug)
    if (match) {
      activeLocation.value = match
      if (import.meta.client) {
        localStorage.setItem('active-location', slug)
      }
    }
  }

  /**
   * Rehydrate active location from localStorage after locations are populated.
   * localStorage always wins over any existing value (including SSR fallback),
   * ensuring returning users get their previously selected theater.
   * Falls back to the first location only if no stored match exists.
   */
  function rehydrate() {
    if (locations.value.length === 0) return

    // Always check localStorage first — it represents the user's explicit choice
    if (import.meta.client) {
      const stored = localStorage.getItem('active-location')
      if (stored) {
        const match = locations.value.find((l) => l.slug === stored)
        if (match) {
          activeLocation.value = match
          return
        }
      }
    }

    // Only fall back if no active location is set yet
    if (!activeLocation.value) {
      activeLocation.value = locations.value[0] ?? null
    }
  }

  // Rehydrate whenever locations change
  watch(locations, () => {
    rehydrate()
  }, { immediate: true })

  async function fetchLocations(): Promise<void> {
    try {
      const response = await apiFetch<{ data: Location[] }>('/api/locations')
      locations.value = response.data
    } catch {
      // Graceful degradation — locations stays empty, activeLocation stays null
    }
  }

  return {
    locations,
    activeLocation,
    setLocation,
    fetchLocations,
  }
}
