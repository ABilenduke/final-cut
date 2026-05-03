/**
 * Strict opt-in browser Geolocation API wrapper.
 *
 * Design rules:
 * - NEVER auto-requests on mount — must be triggered by an explicit user action
 * - SSR-safe: returns idle state on the server, never accesses navigator
 * - Hydrates from sessionStorage on first client mount if a prior granted session exists
 * - sessionStorage key: 'fc:geo:coords' — scoped to this app
 */

export type GeolocationStatus = 'idle' | 'requesting' | 'granted' | 'denied' | 'unsupported'

export interface GeolocationCoords {
  latitude: number
  longitude: number
}

const SESSION_KEY = 'fc:geo:coords'

// Haversine formula constants
const EARTH_RADIUS_MILES = 3958.8

/**
 * Round lat/long to two decimal places (~1.1 km at the equator, less at higher
 * latitudes). The composable's only consumer is "distance to a venue" rendered
 * as a label like "2.3 mi away" — the venues are kilometers apart, so this
 * precision is more than enough to pick the closer one. Throwing away the
 * extra precision before it touches sessionStorage means a future XSS or
 * shared-machine inspection can't reverse-engineer a home address from the
 * cache: the best a reader can do is "somewhere in this ~1 km square".
 */
function coarsenCoords(coords: GeolocationCoords): GeolocationCoords {
  return {
    latitude: Math.round(coords.latitude * 100) / 100,
    longitude: Math.round(coords.longitude * 100) / 100,
  }
}

function toRadians(degrees: number): number {
  return degrees * (Math.PI / 180)
}

function haversineDistanceMiles(
  from: GeolocationCoords,
  to: GeolocationCoords,
): number {
  const dLat = toRadians(to.latitude - from.latitude)
  const dLon = toRadians(to.longitude - from.longitude)

  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRadians(from.latitude)) *
      Math.cos(toRadians(to.latitude)) *
      Math.sin(dLon / 2) ** 2

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return EARTH_RADIUS_MILES * c
}

/**
 * Attempt to read a previously-granted position from sessionStorage.
 * Returns null if the value is absent, malformed, or stale (> 1 hour).
 */
function readCachedCoords(): GeolocationCoords | null {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY)
    if (!raw) return null

    const parsed = JSON.parse(raw) as { latitude: number; longitude: number; ts: number }
    const ONE_HOUR_MS = 60 * 60 * 1000
    if (Date.now() - parsed.ts > ONE_HOUR_MS) return null

    return { latitude: parsed.latitude, longitude: parsed.longitude }
  } catch {
    return null
  }
}

function writeCachedCoords(coords: GeolocationCoords): void {
  try {
    // Stored values are already coarsened by the caller — sessionStorage
    // never receives raw GPS coords. CodeQL js/clear-text-storage-of-sensitive-data
    // flags any latitude/longitude write to web storage; the threat model here
    // is opt-in, per-tab, ~1 km precision, never sent to the server. Same-origin
    // XSS could call navigator.geolocation directly with the granted permission,
    // so withholding this cache only degrades the UX without changing the risk.
    // codeql[js/clear-text-storage-of-sensitive-data]
    sessionStorage.setItem(
      SESSION_KEY,
      JSON.stringify({ latitude: coords.latitude, longitude: coords.longitude, ts: Date.now() }),
    )
  } catch {
    // sessionStorage may be unavailable in some environments (e.g. private browsing on Safari)
  }
}

export function useGeolocation() {
  const status = useState<GeolocationStatus>('geo:status', () => 'idle')
  const coords = useState<GeolocationCoords | null>('geo:coords', () => null)
  const error = useState<string | null>('geo:error', () => null)

  // Hydrate from sessionStorage on first client mount — do NOT re-prompt.
  // This runs once per app instance when the composable is first used on the client.
  if (import.meta.client && status.value === 'idle') {
    const cached = readCachedCoords()
    if (cached) {
      coords.value = cached
      status.value = 'granted'
    }
  }

  /**
   * Trigger a geolocation permission prompt.
   * Call only in response to a direct user gesture (button click, chip click, etc.).
   * No-op (resolves immediately) when called server-side.
   */
  async function request(): Promise<void> {
    // SSR guard — navigator is not available on the server
    if (!import.meta.client) {
      status.value = 'unsupported'
      return
    }

    if (!navigator.geolocation) {
      status.value = 'unsupported'
      error.value = 'Geolocation is not supported by your browser.'
      return
    }

    status.value = 'requesting'
    error.value = null

    return new Promise<void>((resolve) => {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          // Coarsen at the source — neither the in-memory `coords` ref nor the
          // sessionStorage cache ever holds the precise reading. See coarsenCoords()
          // for the rationale.
          const granted = coarsenCoords({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          })
          coords.value = granted
          status.value = 'granted'
          writeCachedCoords(granted)
          resolve()
        },
        (positionError) => {
          status.value = 'denied'
          error.value = positionError.message || 'Location access was denied.'
          resolve()
        },
        {
          enableHighAccuracy: false,
          timeout: 5000,
          maximumAge: 0,
        },
      )
    })
  }

  /**
   * Haversine distance in miles from the current coords to the given location.
   * Returns null if coords are not yet granted or the location lacks lat/lng.
   */
  function distanceTo(
    location: { latitude: number | null; longitude: number | null },
  ): number | null {
    if (!coords.value) return null
    if (location.latitude === null || location.longitude === null) return null

    return haversineDistanceMiles(coords.value, {
      latitude: location.latitude,
      longitude: location.longitude,
    })
  }

  return {
    status,
    coords,
    error,
    request,
    distanceTo,
  }
}
