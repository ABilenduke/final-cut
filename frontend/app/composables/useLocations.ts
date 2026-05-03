import type { Location } from '~/types/location'
import { apiFetch } from '~/utils/api'

/**
 * useLocations — manages the user's activeLocation *preference* for the
 * booking flow only.
 *
 * activeLocation is deliberately NOT hydrated at app boot. It starts as null
 * and is only set when:
 *   1. The user explicitly picks a venue (setLocation → also writes localStorage)
 *   2. The booking flow calls restoreActiveLocation() before showing the seat
 *      picker, so it can pre-select a sensible default.
 *
 * Browse pages must use usePublicLocations() for cross-location catalog
 * fetches — they must never gate content on activeLocation.
 */
export function useLocations() {
  const locations = useState<Location[]>('locations', () => [])
  // Lazy init — null until the booking flow or the user explicitly sets it.
  const activeLocation = useState<Location | null>('active-location', () => null)
  const locationsReady = useState<boolean>('locations-ready', () => false)

  /**
   * Explicitly pick a venue. Writes to localStorage for future booking-flow
   * restoration. Safe to call from any context but should only be wired to
   * explicit user actions (LocationPreferenceSwitcher, booking-time picker).
   */
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
   * Restore the user's stored preference from localStorage into activeLocation.
   * Call this lazily from booking-flow code (e.g. before showing the seat
   * picker) — NOT at app boot or from browse pages.
   *
   * If no stored preference exists, falls back to the first known location so
   * the booking flow always has a non-null default.
   *
   * Client-only (noop on the server where localStorage is unavailable).
   */
  function restoreActiveLocation() {
    if (!import.meta.client) return
    if (locations.value.length === 0) return

    const stored = localStorage.getItem('active-location')
    if (stored) {
      const match = locations.value.find((l) => l.slug === stored)
      if (match) {
        activeLocation.value = match
        return
      }
    }

    // No stored preference or stored slug no longer valid — use first location.
    if (!activeLocation.value) {
      activeLocation.value = locations.value[0] ?? null
    }
  }

  /**
   * Fetch the locations catalog from the API into the shared `locations` ref.
   * Does NOT touch activeLocation — callers that need the booking preference
   * should call restoreActiveLocation() separately after this resolves.
   */
  async function fetchLocations(): Promise<void> {
    try {
      const response = await apiFetch<{ data: Location[] }>('/api/locations')
      locations.value = response.data
    } catch {
      // Graceful degradation — keep any previously loaded locations unchanged
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
    restoreActiveLocation,
    fetchLocations,
    initializeLocations,
  }
}
