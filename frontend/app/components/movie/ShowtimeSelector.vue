<script setup lang="ts">
import type { Showtime } from '~/types/showtime'

const props = defineProps<{
  showtimes: Showtime[]
}>()

const { activeLocation } = useLocations()

// TODO(backend): replace stub format groups when Showtime gains a `format` field.
// Showtimes are currently assigned to a format group deterministically by id hash so
// the UI renders consistently; real assignment happens backend-side later.
type FormatGroup = {
  id: string
  name: string
  accent: string
  tags: string[]
  desc: string
}

const FORMAT_GROUPS: FormatGroup[] = [
  {
    id: '70mm',
    name: '70mm',
    accent: 'Film',
    tags: ['70mm print', 'Atmos', 'Reserved'],
    desc: 'Archival print screenings. No trailers, no ads — the programme begins on time.',
  },
  {
    id: 'imax',
    name: 'IMAX ·',
    accent: 'Atmos',
    tags: ['IMAX', 'Atmos', 'Reserved'],
    desc: 'Reference-grade IMAX screen with Dolby Atmos sound in Auditorium 02.',
  },
  {
    id: 'digital',
    name: 'Digital ·',
    accent: '4K',
    tags: ['4K · Laser', '7.1', 'Reserved'],
    desc: 'Standard 4K digital projection across Auditoriums 03 and 04.',
  },
  {
    id: 'late',
    name: 'The',
    accent: 'Late Show',
    tags: ['70mm', 'Bar until 2 AM', 'Reserved'],
    desc: 'Past-midnight programming for the committed. Drinks served in the auditorium.',
  },
]

const FORMAT_CHIPS = [
  { id: 'all', label: 'All formats' },
  { id: '70mm', label: '70mm Film' },
  { id: 'imax', label: 'IMAX · Atmos' },
  { id: 'digital', label: 'Digital · 4K' },
  { id: 'late', label: 'Late Show' },
  { id: 'members', label: 'Members Only' },
  { id: 'cc', label: 'Closed Captions' },
]

// TODO(backend): wire this to a real filter once Showtime has format metadata.
const activeFormat = ref<string>('all')

/** Convert a Date to YYYY-MM-DD using local components so grouping matches the user's calendar day. */
function toLocalDateKey(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const today = toLocalDateKey(new Date())

// Deterministic stub assignment of showtimes to format groups.
function formatFor(showtimeId: string): string {
  const n = showtimeId.split('').reduce((acc, ch) => acc + ch.charCodeAt(0), 0)
  return FORMAT_GROUPS[n % FORMAT_GROUPS.length].id
}

// Group showtimes by local date
const byDate = computed(() => {
  const groups = new Map<string, Showtime[]>()
  for (const st of props.showtimes) {
    const key = toLocalDateKey(new Date(st.startTime))
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key)!.push(st)
  }
  const sorted = new Map([...groups.entries()].sort(([a], [b]) => a.localeCompare(b)))
  for (const [, list] of sorted) {
    list.sort((a, b) => a.startTime.localeCompare(b.startTime))
  }
  return sorted
})

const availableDates = computed(() => [...byDate.value.keys()])

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
    const hasData = byDate.value.has(key)
    const count = byDate.value.get(key)?.length ?? 0
    return { key, date: d, hasData, count }
  })
})

const activeShowtimes = computed(() => byDate.value.get(activeDate.value) ?? [])

const matrix = computed(() => {
  return FORMAT_GROUPS.map(group => {
    const items = activeShowtimes.value.filter(st => formatFor(st.id) === group.id)
    const minPrice = items.reduce(
      (min, st) => (st.priceStandard < min ? st.priceStandard : min),
      Number.MAX_SAFE_INTEGER,
    )
    return {
      group,
      items,
      minPrice: items.length > 0 ? minPrice : null,
    }
  }).filter(g => g.items.length > 0 || activeFormat.value === g.group.id)
})

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

// Deterministic pseudo-availability matching the design's visual rhythm.
// TODO(backend): replace with real seat-availability data.
function availability(showtimeId: string): { label: string; tone: 'ok' | 'low' | 'out' } {
  const n = showtimeId.split('').reduce((acc, ch) => acc + ch.charCodeAt(0), 0) % 11
  if (n < 2) return { label: '6 seats', tone: 'low' }
  if (n < 6) return { label: `${42 + n * 8} seats`, tone: 'ok' }
  return { label: `${120 + n * 4} seats`, tone: 'ok' }
}

// First slot of the first non-empty group gets the Members badge (visual pattern from the design).
const memberSlotId = computed<string | null>(() => {
  const first = matrix.value[0]
  return first?.items[0]?.id ?? null
})

// Keyboard nav across date strip
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
          <span>Location</span>
          <b>{{ activeLocation?.name || 'All locations' }}</b>
        </div>
      </div>
    </div>

    <!-- Format filter chips (visual only — TODO(backend): wire to real filtering) -->
    <div class="showtime-selector__formats" role="toolbar" aria-label="Showtime filters">
      <button
        v-for="chip in FORMAT_CHIPS"
        :key="chip.id"
        type="button"
        class="showtime-selector__fmt"
        :class="{ 'showtime-selector__fmt--on': activeFormat === chip.id }"
        :aria-pressed="activeFormat === chip.id"
        @click="activeFormat = chip.id"
      >
        {{ chip.label }}
      </button>
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

    <!-- Matrix -->
    <div v-if="matrix.length > 0" class="showtime-selector__matrix">
      <div v-for="row in matrix" :key="row.group.id" class="showtime-selector__group">
        <div class="showtime-selector__fmt-col">
          <span class="showtime-selector__fmt-name">
            {{ row.group.name }} <em>{{ row.group.accent }}</em>
          </span>
          <span class="showtime-selector__fmt-tags">
            <span v-for="tag in row.group.tags" :key="tag">{{ tag }}</span>
          </span>
          <span class="showtime-selector__fmt-desc">{{ row.group.desc }}</span>
          <span v-if="row.minPrice != null" class="showtime-selector__fmt-price">
            From <b>{{ formatCurrency(row.minPrice) }}</b>
          </span>
        </div>
        <div class="showtime-selector__times">
          <NuxtLink
            v-for="st in row.items"
            :key="st.id"
            :to="`/purchase/${st.id}`"
            class="showtime-selector__slot"
            :class="{ 'showtime-selector__slot--recommend': st.id === memberSlotId }"
          >
            <span class="showtime-selector__slot-time">
              {{ timeParts(st.startTime).time }}
              <span class="showtime-selector__slot-mer">{{ timeParts(st.startTime).meridiem }}</span>
            </span>
            <span class="showtime-selector__slot-meta">
              <span
                class="showtime-selector__slot-avail"
                :class="{ 'showtime-selector__slot-avail--low': availability(st.id).tone === 'low' }"
              >
                {{ availability(st.id).label }}
              </span>
              <template v-if="st.id === memberSlotId"> · Early access</template>
            </span>
          </NuxtLink>
        </div>
      </div>
    </div>

    <p v-else class="showtime-selector__empty">
      <template v-if="props.showtimes.length === 0">
        No showtimes available at {{ activeLocation?.name ?? 'this location' }} yet. Check back soon.
      </template>
      <template v-else>
        No showtimes on this day — select another date above.
      </template>
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
  border: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.3); /* token-exception: sub-pixel border */
  border-radius: 0.125rem;
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

/* ——— Format chips ——— */
.showtime-selector__formats {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-bottom: var(--space-lg);
  padding-bottom: var(--space-lg);
  border-bottom: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.2); /* token-exception: sub-pixel divider */
}

.showtime-selector__fmt {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.45rem 0.8rem;
  border-radius: 62.5rem;
  background: transparent;
  border: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.4); /* token-exception: sub-pixel border */
  font-size: 0.75rem;
  letter-spacing: 0.06em;
  color: var(--tertiary);
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color 200ms, color 200ms, border-color 200ms;
}

.showtime-selector__fmt:hover {
  border-color: rgba(var(--secondary-rgb), 0.4);
  color: var(--on-surface);
}

.showtime-selector__fmt--on {
  background: var(--secondary);
  color: var(--surface);
  border-color: var(--secondary);
  font-weight: 500;
}

.showtime-selector__fmt:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
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
  border-radius: 0.125rem;
  background: var(--surface-container);
  border: 0.0625rem solid transparent; /* token-exception: sub-pixel border for hover swap */
  color: inherit;
  font: inherit;
  cursor: pointer;
  position: relative;
  transition: background-color 200ms;
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
  border-radius: 50%;
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

/* ——— Matrix ——— */
.showtime-selector__matrix {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.showtime-selector__group {
  display: grid;
  grid-template-columns: 13.75rem 1fr;
  gap: var(--space-xl);
  padding: var(--space-lg) var(--space-lg) var(--space-lg) 0;
  border-top: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.2); /* token-exception: sub-pixel divider */
}

.showtime-selector__group:last-child {
  border-bottom: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.2); /* token-exception: sub-pixel divider */
}

.showtime-selector__fmt-col {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding-top: 0.25rem;
}

.showtime-selector__fmt-name {
  font-family: var(--font-display);
  font-size: 1.375rem;
  letter-spacing: -0.015em;
  color: var(--on-surface);
  font-weight: 500;
}

.showtime-selector__fmt-name em {
  font-style: italic;
  color: var(--secondary);
}

.showtime-selector__fmt-tags {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-top: 0.2rem;
}

.showtime-selector__fmt-tags span {
  font-size: 0.625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding: 0.2rem 0.45rem;
  background: rgba(42, 42, 42, 0.5);
  border-radius: 0.125rem;
}

.showtime-selector__fmt-desc {
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.45;
  margin-top: 0.35rem;
}

.showtime-selector__fmt-price {
  font-size: 0.6875rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: 0.35rem;
}

.showtime-selector__fmt-price b {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--secondary);
  letter-spacing: -0.01em;
  text-transform: none;
  font-weight: 500;
}

.showtime-selector__times {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(7.75rem, 1fr));
  gap: 0.5rem;
}

.showtime-selector__slot {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
  padding: 0.75rem 0.9rem;
  border-radius: 0.125rem;
  background: var(--surface-container);
  border: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.2); /* token-exception: sub-pixel border */
  text-decoration: none;
  color: inherit;
  position: relative;
  transition: background-color 180ms, border-color 180ms, transform 180ms;
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

.showtime-selector__slot-meta {
  font-size: 0.625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.showtime-selector__slot-avail {
  color: var(--secondary);
}

.showtime-selector__slot-avail--low {
  color: #e6a97a; /* token-exception: low-availability warning hue */
}

.showtime-selector__slot--recommend::before {
  content: 'Members';
  position: absolute;
  top: -0.5rem;
  left: 0.75rem;
  font-size: 0.5625rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  background: var(--secondary);
  color: var(--surface);
  padding: 0.15rem 0.4rem;
  border-radius: 0.125rem;
  font-weight: 500;
}

.showtime-selector__empty {
  color: var(--tertiary);
  margin: 0;
  padding: var(--space-xl) 0;
  text-align: center;
}

@media (max-width: 68.75rem) {
  .showtime-selector__group {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 39.999rem) {
  .showtime-selector__datestrip {
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: minmax(4rem, auto);
  }
}

@media (prefers-reduced-motion: reduce) {
  .showtime-selector__slot,
  .showtime-selector__fmt,
  .showtime-selector__day {
    transition: none;
  }
}
</style>
