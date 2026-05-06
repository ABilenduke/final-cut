/**
 * Locations plugin — intentionally empty.
 *
 * The locations catalog is fetched on-demand by usePublicLocations() on
 * browse pages (SSR-friendly, no boot-time fetch required). The booking
 * flow fetches the catalog itself before showing the seat picker.
 *
 * activeLocation is NOT rehydrated at boot — it is lazy: the booking flow
 * calls useLocations().restoreActiveLocation() when it needs the stored
 * preference. See useLocations.ts for the full rationale.
 */
export default defineNuxtPlugin(() => {
  // intentionally empty — no boot-time location fetch
})
