<script setup lang="ts">
import type { Showtime, ShowtimeLocation } from '~/types/showtime'

const props = defineProps<{
  showtimes: Showtime[]
  movieSlug?: string
}>()

// Geolocation — strictly opt-in; SSR always returns idle/null
const { status: geoStatus, coords: geoCoords, distanceTo } = useGeolocation()

// ─── Date grouping ────────────────────────────────────────────────────────────

/** Convert a Date to YYYY-MM-DD using local components so grouping matches the user's calendar day. */
function toLocalDateKey(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const today = toLocalDateKey(new Date())

const availableDates = computed<string[]>(() => {
  const keys = new Set<string>()
  for (const st of props.showtimes) {
    keys.add(toLocalDateKey(new Date(st.startTime)))
  }
  return [...keys].sort()
})

const activeDate = ref<string>('')

watchEffect(() => {
  if (availableDates.value.length === 0) {
    activeDate.value = ''
    return
  }
  if (activeDate.value && availableDates.value.includes(activeDate.value)) return
  activeDate.value = availableDates.value.includes(today)
    ? today
    : availableDates.value[0]
})

// Up to 7 consecutive days starting from the earliest available date (or today if earlier).
const dateStrip = computed(() => {
  const base = availableDates.value[0] ?? today
  const start = new Date(`${base}T12:00:00Z`)
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(start)
    d.setUTCDate(start.getUTCDate() + i)
    const key = toLocalDateKey(d)
    const count = props.showtimes.filter(
      st => toLocalDateKey(new Date(st.startTime)) === key,
    ).length
    return { key, date: d, hasData: count > 0, count }
  })
})

// Showtimes for the selected date
const showtimesForDate = computed<Showtime[]>(() =>
  props.showtimes.filter(
    st => toLocalDateKey(new Date(st.startTime)) === activeDate.value,
  ),
)

// ─── Venue grouping ───────────────────────────────────────────────────────────

interface VenueSlot {
  id: string
  screenName: string
  priceStandard: number
  time: string
  meridiem: string
}

interface VenueGroup {
  location: ShowtimeLocation
  slots: VenueSlot[]
  /** Distance in miles from user — null when geolocation not granted or location has no coords. */
  distance: number | null
}

const venueGroups = computed<VenueGroup[]>(() => {
  // Collect unique locations from showtimes that have a `location` payload.
  const locMap = new Map<string, ShowtimeLocation>()
  for (const st of showtimesForDate.value) {
    if (st.location && !locMap.has(st.location.slug)) {
      locMap.set(st.location.slug, st.location)
    }
  }

  if (locMap.size === 0) return []

  const groups: VenueGroup[] = []
  for (const [slug, loc] of locMap) {
    const slots = showtimesForDate.value
      .filter(st => st.location?.slug === slug)
      .sort((a, b) => a.startTime.localeCompare(b.startTime))
      .map<VenueSlot>((st) => {
        const { time, meridiem } = timeParts(st.startTime)
        return {
          id: st.id,
          screenName: st.screenName,
          priceStandard: st.priceStandard,
          time,
          meridiem,
        }
      })
    const distance = distanceTo({ latitude: loc.latitude, longitude: loc.longitude })
    groups.push({ location: loc, slots, distance })
  }

  // Sort: when geolocation granted and distances available, sort by distance (closest first).
  // Otherwise, sort alphabetically by name.
  if (geoStatus.value === 'granted' && groups.some(g => g.distance !== null)) {
    groups.sort((a, b) => {
      if (a.distance === null && b.distance === null) return a.location.name.localeCompare(b.location.name)
      if (a.distance === null) return 1
      if (b.distance === null) return -1
      return a.distance - b.distance
    })
  } else {
    groups.sort((a, b) => a.location.name.localeCompare(b.location.name))
  }

  return groups
})

// Track open/closed state per venue slug. Default-expand: closest venue
// (index 0) when geolocation granted, all venues when not.
const venueOpenState = ref<Record<string, boolean>>({})

watch(
  venueGroups,
  (groups) => {
    // Only initialise slots that don't already exist so user toggles persist
    // across reactive re-renders that don't change the slug set.
    groups.forEach((g, i) => {
      if (!(g.location.slug in venueOpenState.value)) {
        venueOpenState.value[g.location.slug] = geoStatus.value !== 'granted' || i === 0
      }
    })
  },
  { immediate: true },
)

// When geolocation status changes (idle → granted), reset open state so the
// closest group expands and the others collapse.
watch(geoStatus, () => {
  venueOpenState.value = {}
})

function isVenueOpen(slug: string): boolean {
  return venueOpenState.value[slug] ?? true
}

function toggleVenue(slug: string): void {
  venueOpenState.value = { ...venueOpenState.value, [slug]: !isVenueOpen(slug) }
}

// ─── Date strip keyboard nav ─────────────────────────────────────────────────

function handleDayKey(event: KeyboardEvent, index: number) {
  const len = dateStrip.value.length
  let next = index
  if (event.key === 'ArrowRight') next = Math.min(index + 1, len - 1)
  else if (event.key === 'ArrowLeft') next = Math.max(index - 1, 0)
  else if (event.key === 'Home') next = 0
  else if (event.key === 'End') next = len - 1
  else return
  event.preventDefault()
  const target = dateStrip.value[next]
  if (target.hasData) activeDate.value = target.key
  const buttons = (event.currentTarget as HTMLElement).parentElement?.querySelectorAll<HTMLElement>('.showtime-selector__day')
  buttons?.[next]?.focus()
}

// ─── Display helpers ──────────────────────────────────────────────────────────

const dowFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'short' })

function dow(d: Date): string {
  return dowFormatter.format(d).toUpperCase()
}

function timeParts(iso: string) {
  const d = new Date(iso)
  const hours = d.getHours()
  const minutes = String(d.getMinutes()).padStart(2, '0')
  const hour12 = ((hours + 11) % 12) + 1
  const meridiem = hours >= 12 ? 'PM' : 'AM'
  return { time: `${hour12}:${minutes}`, meridiem }
}

function formatDistance(miles: number | null): string {
  if (miles === null) return ''
  return `${miles.toFixed(1)} mi away`
}
</script>

<template>
  <div class="showtime-selector">
    <div class="showtime-selector__head">
      <div class="showtime-selector__head-left">
        <div class="bay-eyebrow">Showtimes · This week</div>
        <h2 class="bay-title">Reserve your <em>seat.</em></h2>
      </div>
      <div class="showtime-selector__head-right">
        <div class="showtime-selector__loc-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" />
          </svg>
          <span>Showing at</span>
          <b>All locations</b>
        </div>
      </div>
    </div>

    <!-- Date strip -->
    <div
      v-if="dateStrip.length > 0"
      class="showtime-selector__datestrip"
      role="tablist"
      aria-label="Showtime dates"
    >
      <button
        v-for="(d, index) in dateStrip"
        :key="d.key"
        type="button"
        role="tab"
        class="showtime-selector__day"
        :class="{
          'showtime-selector__day--on': d.key === activeDate,
          'showtime-selector__day--today': d.key === today,
          'showtime-selector__day--empty': !d.hasData,
        }"
        :aria-selected="d.key === activeDate"
        :tabindex="d.key === activeDate ? 0 : -1"
        :disabled="!d.hasData"
        @click="d.hasData && (activeDate = d.key)"
        @keydown="handleDayKey($event, index)"
      >
        <span class="showtime-selector__dow">
          {{ dow(d.date) }}<template v-if="d.key === today"> · Today</template>
        </span>
        <span class="showtime-selector__date-num">{{ d.date.getDate() }}</span>
        <span class="showtime-selector__cnt">
          <template v-if="d.hasData">{{ d.count }} show{{ d.count === 1 ? '' : 's' }}</template>
          <template v-else>No shows</template>
        </span>
      </button>
    </div>

    <!-- Zero showtimes at all — empty state -->
    <template v-if="props.showtimes.length === 0">
      <div class="showtime-selector__empty-state">
        <p class="showtime-selector__empty-text">Showtimes coming soon.</p>
        <NuxtLink
          v-if="movieSlug"
          :to="`/movies/${movieSlug}#notify`"
          class="showtime-selector__notify-link"
        >
          Notify Me
        </NuxtLink>
        <span v-else class="showtime-selector__notify-link showtime-selector__notify-link--static">
          Notify Me
        </span>
      </div>
    </template>

    <!-- Venue groups (one collapsible block per location) -->
    <template v-else-if="venueGroups.length > 0">
      <div class="showtime-selector__venues">
        <div
          v-for="group in venueGroups"
          :key="group.location.slug"
          class="showtime-selector__venue"
          :data-slug="group.location.slug"
        >
          <!-- Venue header / toggle -->
          <button
            type="button"
            class="showtime-selector__venue-hd"
            :aria-expanded="isVenueOpen(group.location.slug)"
            :aria-controls="`venue-times-${group.location.slug}`"
            @click="toggleVenue(group.location.slug)"
          >
            <span class="showtime-selector__venue-name">{{ group.location.name }}</span>
            <span
              v-if="geoStatus === 'granted' && group.distance !== null"
              class="showtime-selector__venue-dist"
            >
              {{ formatDistance(group.distance) }}
            </span>
            <svg
              class="showtime-selector__venue-chevron"
              :class="{ 'showtime-selector__venue-chevron--open': isVenueOpen(group.location.slug) }"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              aria-hidden="true"
            >
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>

          <!-- Time slots panel -->
          <div
            :id="`venue-times-${group.location.slug}`"
            class="showtime-selector__venue-times"
            :class="{ 'showtime-selector__venue-times--open': isVenueOpen(group.location.slug) }"
          >
            <div class="showtime-selector__slots">
              <NuxtLink
                v-for="slot in group.slots"
                :key="slot.id"
                :to="`/purchase/${slot.id}`"
                class="showtime-selector__slot"
              >
                <span class="showtime-selector__slot-time">
                  {{ slot.time }}
                  <span class="showtime-selector__slot-mer">{{ slot.meridiem }}</span>
                </span>
                <span class="showtime-selector__slot-screen">
                  {{ slot.screenName }}
                </span>
                <span class="showtime-selector__slot-price">
                  From {{ formatCurrency(slot.priceStandard) }}
                </span>
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Showtimes exist but none on the selected date -->
    <p v-else class="showtime-selector__empty">
      No showtimes on this day — select another date above.
    </p>
  </div>
</template>

<style scoped>
.showtime-selector {
  display: flex;
  flex-direction: column;
}

/* ——— Head ——— */
.showtime-selector__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-xl);
  margin-bottom: var(--space-xl);
  flex-wrap: wrap;
}

.showtime-selector__head-left {
  flex: 1;
  min-width: 17.5rem;
}

.showtime-selector__head-right {
  display: flex;
  gap: var(--space-sm);
  align-items: center;
  flex-wrap: wrap;
}

.showtime-selector__loc-pill {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.6rem 0.85rem;
  background: var(--surface-container);
  border: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.3);
  border-radius: var(--radius-sm);
  color: var(--on-surface);
  font-size: 0.875rem;
}

.showtime-selector__loc-pill svg {
  color: var(--secondary);
  width: 0.875rem;
  height: 0.875rem;
}

.showtime-selector__loc-pill b {
  font-weight: 500;
}

/* ——— Date strip ——— */
.showtime-selector__datestrip {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.4rem;
  margin-bottom: var(--space-xl);
}

.showtime-selector__day {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.9rem 0.5rem;
  border-radius: var(--radius-sm);
  background: var(--surface-container);
  border: var(--border-hairline) solid transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  position: relative;
  transition: background-color var(--duration-standard);
}

.showtime-selector__day:hover:not(:disabled) {
  background: var(--surface-container-high);
}

.showtime-selector__day:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.showtime-selector__day--on {
  background: var(--primary-container);
  border-color: var(--primary-container);
  color: var(--primary);
}

.showtime-selector__day--on:hover {
  background: var(--primary-container);
}

.showtime-selector__day--today::after {
  content: '';
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
  width: 0.25rem;
  height: 0.25rem;
  border-radius: var(--radius-full);
  background: var(--secondary);
}

.showtime-selector__day--empty {
  opacity: 0.45;
  cursor: not-allowed;
}

.showtime-selector__dow {
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.showtime-selector__day--on .showtime-selector__dow,
.showtime-selector__day--on .showtime-selector__cnt {
  color: rgba(255, 180, 168, 0.75);
}

.showtime-selector__date-num {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  letter-spacing: -0.02em;
  line-height: 1;
  color: var(--on-surface);
}

.showtime-selector__day--on .showtime-selector__date-num {
  color: var(--primary);
}

.showtime-selector__cnt {
  font-size: 0.625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

/* ——— Empty states ——— */
.showtime-selector__empty-state {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-md);
  padding: var(--space-xl) 0;
}

.showtime-selector__empty-text {
  color: var(--tertiary);
  margin: 0;
}

.showtime-selector__notify-link {
  display: inline-flex;
  align-items: center;
  padding: 0.75rem 1.5rem;
  background: var(--primary-container);
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: 0.875rem;
  font-weight: 500;
  letter-spacing: 0.04em;
  text-decoration: none;
  border-radius: var(--radius-sm);
  transition: opacity var(--duration-standard);
}

.showtime-selector__notify-link:hover {
  opacity: 0.85;
}

.showtime-selector__notify-link:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.showtime-selector__notify-link--static {
  cursor: default;
  opacity: 0.7;
}

.showtime-selector__empty {
  color: var(--tertiary);
  margin: 0;
  padding: var(--space-xl) 0;
}

/* ——— Venues ——— */
.showtime-selector__venues {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.showtime-selector__venue {
  border-top: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
}

.showtime-selector__venue:last-child {
  border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
}

.showtime-selector__venue-hd {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  width: 100%;
  padding: var(--space-lg) 0;
  background: none;
  border: none;
  color: inherit;
  font: inherit;
  cursor: pointer;
  text-align: left;
}

.showtime-selector__venue-hd:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.showtime-selector__venue-name {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  letter-spacing: -0.015em;
  color: var(--on-surface);
  flex: 1;
}

.showtime-selector__venue-dist {
  font-size: 0.75rem;
  letter-spacing: 0.06em;
  color: var(--secondary);
  font-family: var(--font-body);
}

.showtime-selector__venue-chevron {
  width: 1rem;
  height: 1rem;
  color: var(--tertiary);
  flex-shrink: 0;
  transition: transform var(--duration-standard);
}

.showtime-selector__venue-chevron--open {
  transform: rotate(180deg);
}

/* Collapsible panel — uses grid-template-rows for smooth animated expand/collapse */
.showtime-selector__venue-times {
  display: grid;
  grid-template-rows: 0fr;
  overflow: hidden;
  transition: grid-template-rows var(--duration-emphasis);
}

.showtime-selector__venue-times--open {
  grid-template-rows: 1fr;
}

.showtime-selector__slots {
  min-height: 0;
  padding-bottom: var(--space-lg);
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(7.75rem, 1fr));
  gap: 0.5rem;
}

/* ——— Time slots ——— */
.showtime-selector__slot {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
  padding: 0.75rem 0.9rem;
  border-radius: var(--radius-sm);
  background: var(--surface-container);
  border: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
  text-decoration: none;
  color: inherit;
  position: relative;
  transition: background-color var(--duration-standard), border-color var(--duration-standard), transform var(--duration-standard);
}

.showtime-selector__slot:hover {
  background: var(--surface-container-high);
  border-color: var(--secondary);
  transform: translateY(-0.0625rem);
}

.showtime-selector__slot:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.showtime-selector__slot-time {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  font-variant-numeric: tabular-nums;
}

.showtime-selector__slot-mer {
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-left: 0.35rem;
  font-weight: 400;
}

.showtime-selector__slot-screen {
  font-size: 0.625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.showtime-selector__slot-price {
  font-size: 0.625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--secondary);
}

@media (max-width: 39.999rem) {
  .showtime-selector__datestrip {
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: minmax(4rem, auto);
  }
}

/* Reduced motion: instant expand/collapse — no animation */
@media (prefers-reduced-motion: reduce) {
  .showtime-selector__venue-times,
  .showtime-selector__venue-chevron,
  .showtime-selector__slot {
    transition: none;
  }
}
</style>
