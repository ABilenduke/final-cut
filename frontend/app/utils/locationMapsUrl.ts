/**
 * Build a Google Maps directions URL from a venue's coordinates.
 * Returns null when either coordinate is unset (the venue is not geocoded yet).
 */
export function locationMapsUrl(location: {
  latitude: number | null
  longitude: number | null
}): string | null {
  if (location.latitude == null || location.longitude == null) {
    return null
  }
  return `https://maps.google.com/?q=${location.latitude},${location.longitude}`
}
