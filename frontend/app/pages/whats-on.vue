<script setup lang="ts">
import type { AccessibilityTag, CalendarEvent } from '~/types/calendar-event'
import { useApiFetch } from '~/utils/api'

const route = useRoute()
const appTimeZone = String(useRuntimeConfig().public.appTimeZone || 'America/New_York')

// Use one serialized date for SSR and hydration. The client refreshes it after
// mount so long-lived sessions can cross a date boundary without stale UI.
const todayDate = useState<string>('whats-on:today-date', () => toLocalDateKey())

if (import.meta.client) {
  onMounted(() => {
    todayDate.value = toLocalDateKey()
  })
}

const todayParts = computed(() => {
  const [yearText, monthText] = todayDate.value.split('-')
  return {
    year: Number(yearText),
    month: Number(monthText),
  }
})

// Read state from URL query params
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
  return todayDate.value
})

const activeFilters = computed(() => {
  const t = route.query.type as string
  return t ? t.split(',').filter(Boolean) : []
})

const activeAccessibilityFilters = computed<AccessibilityTag[]>(() => {
  const a = route.query.accessibility as string
  if (!a) return []
  return a.split(',').filter(Boolean) as AccessibilityTag[]
})

// Build API query params
const typeQuery = computed(() => activeFilters.value.join(',') || undefined)
const accessibilityQuery = computed(() => activeAccessibilityFilters.value.join(',') || undefined)

// Fetch events — reactive query ensures re-fetch on param change
const apiQuery = computed(() => ({
  month: month.value,
  year: year.value,
  ...(typeQuery.value ? { type: typeQuery.value } : {}),
  ...(accessibilityQuery.value ? { accessibility: accessibilityQuery.value } : {}),
}))

const { data: eventsData } = useApiFetch<{ data: CalendarEvent[] }>('/api/calendar/events', {
  query: apiQuery,
  watch: [apiQuery],
})

const events = computed(() => eventsData.value?.data ?? [])

const eventsForSelectedDate = computed(() =>
  events.value.filter(e => e.date === selectedDate.value),
)

// Update URL when filters change
function updateQuery(updates: Record<string, string | undefined>) {
  const query: Record<string, string> = {}
  // Preserve existing query params
  for (const [key, val] of Object.entries(route.query)) {
    if (typeof val === 'string') query[key] = val
  }
  // Apply updates, remove undefined values
  for (const [key, val] of Object.entries(updates)) {
    if (val) {
      query[key] = val
    } else {
      delete query[key]
    }
  }
  navigateTo({ query })
}

function onViewChange(newView: string) {
  updateQuery({ view: newView === 'month' ? undefined : newView })
}

function onFilterChange(filters: string[]) {
  updateQuery({ type: filters.length ? filters.join(',') : undefined })
}

function onAccessibilityFilterChange(filters: AccessibilityTag[]) {
  updateQuery({ accessibility: filters.length ? filters.join(',') : undefined })
}

function onSelectDate(date: string) {
  updateQuery({ date })
}

function onNavigate(payload: { month: number; year: number }) {
  updateQuery({
    month: String(payload.month),
    year: String(payload.year),
  })
}

function toLocalDateKey(date = new Date()): string {
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: appTimeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
  const parts = formatter.formatToParts(date)
  const part = (type: string) => parts.find(p => p.type === type)?.value ?? ''
  return `${part('year')}-${part('month')}-${part('day')}`
}

// SEO
useHead({
  title: 'What\'s On — Final Cut',
  meta: [
    { name: 'description', content: 'Browse showtimes, special events, and accessibility screenings at Final Cut.' },
    { property: 'og:title', content: 'What\'s On — Final Cut' },
    { property: 'og:description', content: 'Browse showtimes, special events, and accessibility screenings at Final Cut.' },
    { property: 'og:type', content: 'website' },
  ],
})
</script>

<template>
  <div class="whats-on-page">
    <div class="container">
      <h1 class="whats-on-page__heading headline-lg">What's On</h1>

      <CalendarFilters
        :active-view="view"
        :active-filters="activeFilters"
        :active-accessibility-filters="activeAccessibilityFilters"
        @view-change="onViewChange"
        @filter-change="onFilterChange"
        @accessibility-filter-change="onAccessibilityFilterChange"
      />

      <div class="whats-on-page__content">
        <CalendarGrid
          :events="events"
          :selected-date="selectedDate"
          :today-date="todayDate"
          :view="view"
          :month="month"
          :year="year"
          @select-date="onSelectDate"
          @navigate="onNavigate"
        />

        <CalendarEventList
          v-if="view !== 'list'"
          :events="eventsForSelectedDate"
          :date="selectedDate"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.whats-on-page {
  padding-block: var(--space-3xl);
}

.whats-on-page__heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-xl) 0;
}

.whats-on-page__content {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
  margin-top: var(--space-xl);
}
</style>
