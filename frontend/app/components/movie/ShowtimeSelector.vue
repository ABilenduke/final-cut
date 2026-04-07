<script setup lang="ts">
import type { Showtime } from '~/types/showtime'

const props = defineProps<{
  showtimes: Showtime[]
}>()

/**
 * Convert a Date to a local YYYY-MM-DD string.
 * Uses local date components so grouping matches the user's calendar day,
 * not the UTC day embedded in the ISO string.
 */
function toLocalDateKey(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

// Group showtimes by local calendar date
const groupedByDate = computed(() => {
  const groups = new Map<string, Showtime[]>()
  for (const st of props.showtimes) {
    const date = toLocalDateKey(new Date(st.startTime))
    if (!groups.has(date)) groups.set(date, [])
    groups.get(date)!.push(st)
  }
  // Sort groups by date, and showtimes within each group by startTime
  const sorted = new Map([...groups.entries()].sort(([a], [b]) => a.localeCompare(b)))
  for (const [, times] of sorted) {
    times.sort((a, b) => a.startTime.localeCompare(b.startTime))
  }
  return sorted
})

const dates = computed(() => [...groupedByDate.value.keys()])

// Default to today if it has showtimes, else first available date
// Uses local date components, not UTC via toISOString()
const today = toLocalDateKey(new Date())
const activeDate = ref('')

// Only set a default when activeDate is empty or no longer valid in the current date set.
// Preserves user's manual tab selection across showtime data refreshes.
watchEffect(() => {
  if (dates.value.length === 0) {
    activeDate.value = ''
    return
  }
  // If user's current selection is still valid, keep it
  if (activeDate.value && dates.value.includes(activeDate.value)) return
  // Otherwise default to today or first available
  activeDate.value = dates.value.includes(today) ? today : dates.value[0]
})

const activeShowtimes = computed(() => {
  return groupedByDate.value.get(activeDate.value) ?? []
})

// Format date for tab label — short format like "Mon, Apr 7"
const tabFormatter = new Intl.DateTimeFormat('en-US', {
  weekday: 'short',
  month: 'short',
  day: 'numeric',
})

function formatTabDate(dateStr: string): string {
  return tabFormatter.format(new Date(dateStr + 'T12:00:00'))
}

// Format time for slot button — "7:00 PM"
const timeFormatter = new Intl.DateTimeFormat('en-US', {
  hour: 'numeric',
  minute: '2-digit',
})

function formatTime(iso: string): string {
  return timeFormatter.format(new Date(iso))
}

function isToday(dateStr: string): boolean {
  return dateStr === today
}

// Keyboard navigation for date tabs (roving tabindex)
function handleTabKeydown(event: KeyboardEvent, index: number) {
  let newIndex = index
  if (event.key === 'ArrowRight') {
    newIndex = Math.min(index + 1, dates.value.length - 1)
  } else if (event.key === 'ArrowLeft') {
    newIndex = Math.max(index - 1, 0)
  } else {
    return
  }
  event.preventDefault()
  activeDate.value = dates.value[newIndex]
  // Focus the new tab
  const tabs = (event.currentTarget as HTMLElement)?.parentElement?.querySelectorAll('[role="tab"]')
  ;(tabs?.[newIndex] as HTMLElement)?.focus()
}
</script>

<template>
  <div class="showtime-selector">
    <!-- No showtimes at all -->
    <p v-if="showtimes.length === 0" class="showtime-selector__empty body-sm">
      No showtimes available
    </p>

    <template v-else>
      <!-- Date tabs -->
      <div class="showtime-selector__tabs" role="tablist" aria-label="Showtime dates">
        <button
          v-for="(date, index) in dates"
          :key="date"
          role="tab"
          :id="`tab-${date}`"
          :aria-selected="date === activeDate"
          :aria-controls="`panel-${date}`"
          :tabindex="date === activeDate ? 0 : -1"
          class="showtime-selector__tab label-lg"
          :class="{
            'showtime-selector__tab--active': date === activeDate,
            'showtime-selector__tab--today': isToday(date),
          }"
          @click="activeDate = date"
          @keydown="handleTabKeydown($event, index)"
        >
          {{ formatTabDate(date) }}
          <span v-if="isToday(date)" class="showtime-selector__today-badge label-md">Today</span>
        </button>
      </div>

      <!-- Time slots panel -->
      <div
        :id="`panel-${activeDate}`"
        role="tabpanel"
        :aria-labelledby="`tab-${activeDate}`"
        class="showtime-selector__panel"
      >
        <div v-if="activeShowtimes.length === 0" class="showtime-selector__empty body-sm">
          No showtimes for this date
        </div>
        <div v-else class="showtime-selector__slots">
          <NuxtLink
            v-for="st in activeShowtimes"
            :key="st.id"
            :to="`/purchase/${st.id}`"
            class="showtime-selector__slot"
          >
            <time :datetime="st.startTime" class="showtime-selector__time label-lg">
              {{ formatTime(st.startTime) }}
            </time>
            <span class="showtime-selector__price label-md">
              {{ formatCurrency(st.priceStandard) }}
            </span>
          </NuxtLink>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.showtime-selector__tabs {
  display: flex;
  gap: var(--space-xs);
  overflow-x: auto;
  padding-bottom: var(--space-xs);
}

.showtime-selector__tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2xs);
  padding: var(--space-xs) var(--space-sm);
  border: none;
  border-radius: 0.125rem;
  background-color: var(--surface-container-high);
  color: var(--on-surface);
  cursor: pointer;
  white-space: nowrap;
  font-family: var(--font-body);
  font-size: var(--type-label-lg);
  transition: background-color var(--duration-micro) var(--ease-standard);
}

.showtime-selector__tab:hover {
  background-color: var(--surface-container-high);
}

.showtime-selector__tab--active {
  background-color: var(--primary-container);
  color: var(--primary);
}

.showtime-selector__tab--active:hover {
  background-color: var(--primary-container);
}

/* Focus uses outline due to horizontal scroll clipping */
.showtime-selector__tab:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.showtime-selector__today-badge {
  color: var(--secondary);
}

.showtime-selector__panel {
  margin-top: var(--space-md);
}

.showtime-selector__slots {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-sm);
}

.showtime-selector__slot {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2xs);
  padding: var(--space-sm) var(--space-md);
  background-color: transparent;
  border: none;
  border-radius: 0.125rem;
  text-decoration: none;
  cursor: pointer;
  transition: background-color var(--duration-micro) var(--ease-standard);
}

.showtime-selector__slot:hover {
  background-color: var(--surface-container-high);
}

.showtime-selector__slot:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.showtime-selector__time {
  color: var(--secondary);
}

.showtime-selector__price {
  color: var(--tertiary);
}

.showtime-selector__empty {
  color: var(--tertiary);
  margin: 0;
}
</style>
