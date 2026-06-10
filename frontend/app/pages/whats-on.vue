<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'
import { useApiFetch } from '~/utils/api'
import { useBridgeFilters } from '~/composables/useBridgeFilters'
import { toLocalDateKey } from '~/utils/formatDate'

const route = useRoute()
const appTimeZone = String(useRuntimeConfig().public.appTimeZone || 'America/New_York')

// Use one serialized date for SSR and hydration. The client refreshes after
// mount so long-lived sessions can cross a date boundary without stale UI.
const todayDate = useState<string>('whats-on:today-date', () => currentAppTodayKey())

if (import.meta.client) {
  onMounted(() => {
    todayDate.value = currentAppTodayKey()
  })
}

const todayParts = computed(() => {
  const [yearText, monthText, dayText] = todayDate.value.split('-')
  return {
    year: Number(yearText),
    month: Number(monthText),
    day: Number(dayText),
  }
})

const month = computed(() => {
  const m = Number(route.query.month)
  return m >= 1 && m <= 12 ? m : todayParts.value.month
})

const year = computed(() => {
  const y = Number(route.query.year)
  return y > 0 ? y : todayParts.value.year
})

const view = computed<'month' | 'week' | 'list'>(() => {
  const v = route.query.view as string
  if (v === 'week' || v === 'list') return v
  return 'month'
})

const selectedDate = computed(() => {
  const d = route.query.date as string
  if (d && /^\d{4}-\d{2}-\d{2}$/.test(d)) return d
  // Default: today if it's in the visible month, otherwise the 1st.
  if (
    todayParts.value.year === year.value
    && todayParts.value.month === month.value
  ) {
    return todayDate.value
  }
  return toLocalDateKey(year.value, month.value, 1)
})

const filters = useBridgeFilters()

let hydratedFromUrl = false
function hydrateFiltersFromRoute() {
  if (hydratedFromUrl) return
  hydratedFromUrl = true
  if (route.query.chips !== undefined) {
    filters.fromQueryValue(route.query.chips as string | string[])
    return
  }
  filters.fromLegacyQuery(
    route.query.type as string | string[] | undefined,
    route.query.accessibility as string | string[] | undefined,
  )
}
hydrateFiltersFromRoute()

// Always fetch the full month (no API filter) and apply chip filtering client-side
// — chip toggles are a union model that the existing single-axis API filter can't
// represent.
const apiQuery = computed(() => ({
  month: month.value,
  year: year.value,
}))

const { data: eventsData } = useApiFetch<{ data: CalendarEvent[] }>(
  '/api/calendar/events',
  {
    query: apiQuery,
    watch: [apiQuery],
  },
)

const allEvents = computed(() => eventsData.value?.data ?? [])

const visibleEvents = computed(() =>
  allEvents.value.filter((e) => filters.isVisible(e)),
)

const eventsForSelectedDate = computed(() =>
  visibleEvents.value
    .filter((e) => e.date === selectedDate.value)
    .sort((a, b) => a.startTime.localeCompare(b.startTime)),
)

const monthLabel = computed(() =>
  new Date(year.value, month.value - 1, 1).toLocaleDateString('en-US', { month: 'long' }),
)

const todayLabel = computed(() =>
  new Date(
    todayParts.value.year,
    todayParts.value.month - 1,
    todayParts.value.day,
  ).toLocaleDateString('en-US', { day: 'numeric', month: 'short' }),
)

function updateQuery(updates: Record<string, string | undefined>) {
  const query: Record<string, string> = {}
  for (const [key, val] of Object.entries(route.query)) {
    if (typeof val === 'string') query[key] = val
  }
  // `undefined` strips the param; `''` is preserved because some encodings (notably
  // `?chips=` for "all chips off") need an empty string to round-trip through
  // reload.
  for (const [key, val] of Object.entries(updates)) {
    if (val === undefined || val === null) {
      delete query[key]
    } else {
      query[key] = val
    }
  }
  if ('chips' in updates) {
    delete query.type
    delete query.accessibility
  }
  if (queriesEqual(route.query, query)) return
  navigateTo({ query })
}

function queriesEqual(
  a: Record<string, unknown>,
  b: Record<string, string>,
): boolean {
  const aKeys = Object.keys(a).filter((k) => typeof a[k] === 'string')
  const bKeys = Object.keys(b)
  if (aKeys.length !== bKeys.length) return false
  return aKeys.every((k) => a[k] === b[k])
}

function onViewChange(newView: 'month' | 'week' | 'list') {
  updateQuery({ view: newView === 'month' ? undefined : newView })
}

function onSelectDate(date: string) {
  updateQuery({ date: date === todayDate.value ? undefined : date })
  if (import.meta.client && window.matchMedia('(max-width: 80rem)').matches) {
    drawerOpen.value = true
  }
}

function onPrev() {
  let newMonth = month.value - 1
  let newYear = year.value
  if (newMonth < 1) {
    newMonth = 12
    newYear -= 1
  }
  updateQuery({
    month: String(newMonth),
    year: String(newYear),
    date: undefined,
  })
}

function onNext() {
  let newMonth = month.value + 1
  let newYear = year.value
  if (newMonth > 12) {
    newMonth = 1
    newYear += 1
  }
  updateQuery({
    month: String(newMonth),
    year: String(newYear),
    date: undefined,
  })
}

function onToday() {
  updateQuery({
    month: undefined,
    year: undefined,
    date: undefined,
  })
}

watch(
  () => Array.from(filters.activeChips.value),
  () => {
    if (!hydratedFromUrl) return
    updateQuery({ chips: filters.toQueryValue() })
  },
  { deep: false },
)

const drawerOpen = ref(false)
function onDrawerClose() {
  drawerOpen.value = false
}

function currentAppTodayKey(date = new Date()): string {
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: appTimeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
  const parts = formatter.formatToParts(date)
  const part = (type: string) => parts.find((p) => p.type === type)?.value ?? ''
  return `${part('year')}-${part('month')}-${part('day')}`
}

useHead({
  title: "What's On — Final Cut",
  meta: [
    {
      name: 'description',
      content:
        'Browse showtimes, special events, and accessibility screenings at Final Cut.',
    },
    { property: 'og:title', content: "What's On — Final Cut" },
    {
      property: 'og:description',
      content:
        'Browse showtimes, special events, and accessibility screenings at Final Cut.',
    },
    { property: 'og:type', content: 'website' },
  ],
})
</script>

<template>
  <div class="whats-on-bridge">
    <BridgeProgrammeToolbar
      :month-label="monthLabel"
      :year="year"
      :view="view"
      :today-label="todayLabel"
      @view-change="onViewChange"
      @prev="onPrev"
      @next="onNext"
      @today="onToday"
    />

    <BridgeFilterRibbon />

    <div class="whats-on-bridge__layout">
      <BridgeMonthGrid
        v-if="view === 'month'"
        :events="visibleEvents"
        :selected-date="selectedDate"
        :today-date="todayDate"
        :month="month"
        :year="year"
        @select-date="onSelectDate"
      />
      <BridgeWeekStrip
        v-else-if="view === 'week'"
        :events="visibleEvents"
        :selected-date="selectedDate"
        :today-date="todayDate"
        :month="month"
        :year="year"
        @select-date="onSelectDate"
      />
      <BridgeAgendaList
        v-else
        :events="visibleEvents"
        :selected-date="selectedDate"
        :today-date="todayDate"
        @select-date="onSelectDate"
      />

      <BridgeDetailRail
        :selected-date="selectedDate"
        :events="eventsForSelectedDate"
      />
    </div>

    <BridgeDetailDrawer
      :open="drawerOpen"
      :selected-date="selectedDate"
      :events="eventsForSelectedDate"
      @close="onDrawerClose"
    />
  </div>
</template>

<style scoped>
.whats-on-bridge {
  padding: var(--space-2xl) var(--space-2xl) var(--space-3xl);
  max-width: 90rem;
  margin: 0 auto;
}

.whats-on-bridge__layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-xl);
  align-items: flex-start;
}

@media (min-width: 80rem) {
  .whats-on-bridge__layout {
    grid-template-columns: 1fr 26rem;
  }
}

@media (max-width: 60rem) {
  .whats-on-bridge {
    padding: var(--space-xl) var(--space-md) var(--space-2xl);
  }
}
</style>
