import type { Location } from '~/types/location'
import { apiFetch } from '~/utils/api'

export function useLocations() {
  const locations = useState<Location[]>('locations', () => [])
  const activeLocation = useState<Location | null>('active-location', () => null)
  const locationsReady = useState<boolean>('locations-ready', () => false)

  function setLocation(slug: string) {
    const match = locations.value.find((l) => l.slug === slug)
    if (match) {
      activeLocation.value = match
      if (import.meta.client) {
        localStorage.setItem('active-location', slug)
      }
    }
  }

  function rehydrate() {
    if (locations.value.length === 0) return

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

  async function fetchLocations(): Promise<void> {
    try {
      const response = await apiFetch<{ data: Location[] }>('/api/locations')
      locations.value = response.data
      rehydrate()
    } catch {
      // Graceful degradation — keep any previously loaded locations and activeLocation unchanged
    }
  }

  async function initializeLocations(): Promise<void> {
    await fetchLocations()
    locationsReady.value = true
  }

  return {
    locations,
    activeLocation,
    locationsReady,
    setLocation,
    fetchLocations,
    initializeLocations,
  }
}
