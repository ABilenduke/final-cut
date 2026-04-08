<script setup lang="ts">
import type { AccessibilityTag } from '~/types/calendar-event'

const route = useRoute()

// Read state from URL query params
const month = computed(() => {
  const m = Number(route.query.month)
  return m >= 1 && m <= 12 ? m : new Date().getMonth() + 1
})

const year = computed(() => {
  const y = Number(route.query.year)
  return y > 0 ? y : new Date().getFullYear()
})

const view = computed<'month' | 'week' | 'list'>(() => {
  const v = route.query.view as string
  if (v === 'week' || v === 'list') return v
  return 'month'
})

const selectedDate = computed(() => {
  const d = route.query.date as string
  if (d && /^\d{4}-\d{2}-\d{2}$/.test(d)) return d
  // Default to today
  const now = new Date()
  const yyyy = now.getFullYear()
  const mm = String(now.getMonth() + 1).padStart(2, '0')
  const dd = String(now.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
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

// Fetch events
const { getEvents } = useCalendarEvents()
const { data: eventsData } = getEvents(month.value, year.value, typeQuery.value, accessibilityQuery.value)

// Re-fetch when query params change
watch([month, year, typeQuery, accessibilityQuery], () => {
  // navigateTo handles the re-render via route change
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

// SEO
useHead({
  title: 'What\'s On — Final Cut',
  meta: [
    { name: 'description', content: 'Browse showtimes, special events, and accessibility screenings at Final Cut.' },
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
