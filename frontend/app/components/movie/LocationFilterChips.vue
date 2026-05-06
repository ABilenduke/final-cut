<script setup lang="ts">
/**
 * LocationFilterChips — Location filter chip row for /movies.
 *
 * Renders an "All Locations" chip plus one chip per venue. Clicking a chip
 * writes `?location=<slug>` to the URL query (preserving other params).
 * Clicking "All Locations" removes the `?location=` param.
 *
 * Post-hydration enhancement: when `useGeolocation.status === 'granted'` and
 * no manual `?location=` filter is active, renders a non-binding suggestion
 * chip "Filter to nearest: {Closest Location Name}". Clicking the suggestion
 * applies that filter. The suggestion never auto-triggers geolocation.
 *
 * SSR ships the chip row without the suggestion chip (status is always 'idle'
 * on the server). The suggestion only appears after client hydration resolves
 * geolocation as 'granted'.
 *
 * URL is the source of truth — no internal state, no localStorage.
 * Active/inactive chip state is derived from `route.query.location`.
 */

import type { Location } from '~/types/location'

const props = defineProps<{
  /** All venues from usePublicLocations — used to build the chip row. */
  locations: Location[]
}>()

const route = useRoute()
const router = useRouter()

/** Currently active location slug from URL (undefined = all locations). */
const activeSlug = computed<string | undefined>(() => {
  const val = route.query.location
  return typeof val === 'string' && val.length > 0 ? val : undefined
})

function applyFilter(slug: string | undefined) {
  const query = { ...route.query }
  if (slug) {
    query.location = slug
  } else {
    delete query.location
  }
  router.push({ query })
}

// ── Geolocation suggestion (client-side only) ─────────────────────────────
const { status: geoStatus, distanceTo } = useGeolocation()

/**
 * Closest location by Haversine distance. Computed after hydration once
 * `geoStatus === 'granted'`. Null when no coords or all locations lack
 * lat/lng. Returning null suppresses the suggestion chip.
 */
const closestLocation = computed<Location | null>(() => {
  if (geoStatus.value !== 'granted') return null
  if (props.locations.length === 0) return null

  let closest: Location | null = null
  let closestDist = Infinity

  for (const loc of props.locations) {
    const dist = distanceTo({ latitude: loc.latitude, longitude: loc.longitude })
    if (dist !== null && dist < closestDist) {
      closestDist = dist
      closest = loc
    }
  }

  return closest
})

/**
 * Show the suggestion only when:
 * - Geolocation is granted
 * - We found a closest location
 * - The user has NOT already set a ?location= filter manually
 */
const showSuggestion = computed<boolean>(
  () =>
    geoStatus.value === 'granted' &&
    closestLocation.value !== null &&
    activeSlug.value === undefined,
)
</script>

<template>
  <div class="location-chips" role="group" aria-label="Filter movies by location">
    <!-- Chip row: All Locations + one per venue -->
    <div class="location-chips__row">
      <!-- "All Locations" chip -->
      <button
        type="button"
        class="location-chips__chip"
        :class="{ 'location-chips__chip--active': activeSlug === undefined }"
        :aria-pressed="activeSlug === undefined"
        @click="applyFilter(undefined)"
      >
        All Locations
      </button>

      <!-- Per-venue chips -->
      <button
        v-for="loc in locations"
        :key="loc.slug"
        type="button"
        class="location-chips__chip"
        :class="{ 'location-chips__chip--active': activeSlug === loc.slug }"
        :aria-pressed="activeSlug === loc.slug"
        @click="applyFilter(loc.slug)"
      >
        {{ loc.name }}
      </button>
    </div>

    <!-- Post-hydration suggestion chip — client-only, not rendered during SSR -->
    <div
      v-if="showSuggestion && closestLocation"
      class="location-chips__suggestion"
    >
      <span class="location-chips__suggestion-label">Nearest you</span>
      <button
        type="button"
        class="location-chips__chip location-chips__chip--suggestion"
        :aria-label="`Filter to nearest location: ${closestLocation.name}`"
        @click="applyFilter(closestLocation.slug)"
      >
        Filter to nearest: {{ closestLocation.name }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.location-chips {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.location-chips__row {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  align-items: center;
}

/* Chip base — matches the status chip visual language from the movies page */
.location-chips__chip {
  padding: 0.5rem 0.9rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.45);
  border-radius: 999px; /* token-exception: editorial pill — same as status filter chips */
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--tertiary);
  background-color: var(--surface-container-low);
  cursor: pointer;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard);
}

.location-chips__chip:hover {
  border-color: rgb(var(--secondary-rgb) / 0.4);
  color: var(--on-surface);
}

/* Active state: gold accent (--secondary) per design system § Chips */
.location-chips__chip--active {
  border-color: var(--secondary);
  color: var(--secondary);
  background-color: rgb(var(--secondary-rgb) / 0.06);
}

/* Suggestion chip: steel info accent to visually distinguish it as a hint,
 * not a committed filter selection. Resolves to --state-info per token spec. */
.location-chips__chip--suggestion {
  border-color: rgb(var(--state-info-rgb, 90 138 160) / 0.5);
  color: var(--state-info, #5a8aa0);
}

.location-chips__chip--suggestion:hover {
  border-color: var(--state-info, #5a8aa0);
  color: var(--on-surface);
}

/* Suggestion row */
.location-chips__suggestion {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.location-chips__suggestion-label {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding-right: var(--space-sm);
  border-right: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
}

@media (max-width: 40rem) {
  .location-chips__chip {
    font-size: 0.6875rem;
    padding: 0.45rem 0.75rem;
  }
}
</style>
