<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'

const props = withDefaults(defineProps<{
  events: CalendarEvent[]
  selectedDate: string
  todayDate?: string
  view?: 'month' | 'week' | 'list'
  month: number
  year: number
}>(), {
  view: 'month',
})

const emit = defineEmits<{
  'select-date': [date: string]
  'navigate': [payload: { month: number; year: number }]
}>()

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as const

const focusedDate = ref(props.selectedDate)

// Title for navigation display
const monthTitle = computed(() => {
  const d = new Date(props.year, props.month - 1, 1)
  return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

// Build the 6×7 grid of dates for the month view
const monthDays = computed(() => {
  const firstDay = new Date(props.year, props.month - 1, 1)
  const lastDay = new Date(props.year, props.month, 0)

  // Monday-based: getDay() returns 0=Sun, so shift to Mon=0
  let startDow = firstDay.getDay() - 1
  if (startDow < 0) startDow = 6

  const days: Array<{ date: string; outsideMonth: boolean }> = []

  // Fill previous month days
  for (let i = startDow - 1; i >= 0; i--) {
    const d = new Date(props.year, props.month - 1, -i)
    days.push({ date: toISODate(d), outsideMonth: true })
  }

  // Fill current month days
  for (let d = 1; d <= lastDay.getDate(); d++) {
    const date = new Date(props.year, props.month - 1, d)
    days.push({ date: toISODate(date), outsideMonth: false })
  }

  // Fill remaining to complete the grid (6 rows × 7 cols = 42 cells)
  const remaining = 42 - days.length
  for (let i = 1; i <= remaining; i++) {
    const d = new Date(props.year, props.month, i)
    days.push({ date: toISODate(d), outsideMonth: true })
  }

  return days
})

// Build week view: 7 days centered on selected date
const weekDays = computed(() => {
  const selected = new Date(`${props.selectedDate}T12:00:00Z`)
  let dow = selected.getUTCDay() - 1
  if (dow < 0) dow = 6
  const monday = new Date(selected)
  monday.setUTCDate(monday.getUTCDate() - dow)

  const days: Array<{ date: string; outsideMonth: boolean }> = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(monday)
    d.setUTCDate(d.getUTCDate() + i)
    days.push({
      date: toISODate(d),
      outsideMonth: d.getUTCMonth() + 1 !== props.month,
    })
  }
  return days
})

// List view: events for the month sorted by date
const listEvents = computed(() => {
  return [...props.events].sort((a, b) => a.startTime.localeCompare(b.startTime))
})

// Group list events by date
const listEventsByDate = computed(() => {
  const groups: Array<{ date: string; events: CalendarEvent[] }> = []
  const map = new Map<string, CalendarEvent[]>()
  for (const event of listEvents.value) {
    const d = event.date
    if (!map.has(d)) {
      const arr: CalendarEvent[] = []
      map.set(d, arr)
      groups.push({ date: d, events: arr })
    }
    map.get(d)!.push(event)
  }
  return groups
})

// Map events to dates for day cells
const eventsByDate = computed(() => {
  const map = new Map<string, CalendarEvent[]>()
  for (const event of props.events) {
    const d = event.date
    if (!map.has(d)) map.set(d, [])
    map.get(d)!.push(event)
  }
  return map
})

const todayStr = computed(() => props.todayDate ?? toISODate(new Date()))

function toISODate(d: Date): string {
  const year = d.getFullYear?.() ?? d.getUTCFullYear()
  const month = String((d.getFullYear ? d.getMonth() : d.getUTCMonth()) + 1).padStart(2, '0')
  const day = String(d.getFullYear ? d.getDate() : d.getUTCDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function navigatePrev() {
  if (props.view === 'week') {
    const selected = new Date(`${props.selectedDate}T12:00:00Z`)
    selected.setUTCDate(selected.getUTCDate() - 7)
    const newDate = toISODate(selected)
    emit('select-date', newDate)
    if (selected.getUTCMonth() + 1 !== props.month) {
      emit('navigate', { month: selected.getUTCMonth() + 1, year: selected.getUTCFullYear() })
    }
  } else {
    const newMonth = props.month === 1 ? 12 : props.month - 1
    const newYear = props.month === 1 ? props.year - 1 : props.year
    emit('navigate', { month: newMonth, year: newYear })
  }
}

function navigateNext() {
  if (props.view === 'week') {
    const selected = new Date(`${props.selectedDate}T12:00:00Z`)
    selected.setUTCDate(selected.getUTCDate() + 7)
    const newDate = toISODate(selected)
    emit('select-date', newDate)
    if (selected.getUTCMonth() + 1 !== props.month) {
      emit('navigate', { month: selected.getUTCMonth() + 1, year: selected.getUTCFullYear() })
    }
  } else {
    const newMonth = props.month === 12 ? 1 : props.month + 1
    const newYear = props.month === 12 ? props.year + 1 : props.year
    emit('navigate', { month: newMonth, year: newYear })
  }
}

// Keyboard navigation
function handleKeydown(e: KeyboardEvent) {
  const current = new Date(`${focusedDate.value}T12:00:00Z`)
  let newDate: Date | null = null

  switch (e.key) {
    case 'ArrowRight':
      e.preventDefault()
      newDate = new Date(current)
      newDate.setUTCDate(newDate.getUTCDate() + 1)
      break
    case 'ArrowLeft':
      e.preventDefault()
      newDate = new Date(current)
      newDate.setUTCDate(newDate.getUTCDate() - 1)
      break
    case 'ArrowDown':
      e.preventDefault()
      newDate = new Date(current)
      newDate.setUTCDate(newDate.getUTCDate() + 7)
      break
    case 'ArrowUp':
      e.preventDefault()
      newDate = new Date(current)
      newDate.setUTCDate(newDate.getUTCDate() - 7)
      break
    case 'Home':
      e.preventDefault()
      newDate = new Date(Date.UTC(props.year, props.month - 1, 1, 12))
      break
    case 'End':
      e.preventDefault()
      newDate = new Date(Date.UTC(props.year, props.month, 0, 12))
      break
    case 'PageDown':
      e.preventDefault()
      navigateNext()
      return
    case 'PageUp':
      e.preventDefault()
      navigatePrev()
      return
  }

  if (newDate) {
    focusedDate.value = toISODate(newDate)
    // If navigated outside current month, emit navigate
    if (newDate.getUTCMonth() + 1 !== props.month || newDate.getUTCFullYear() !== props.year) {
      emit('navigate', { month: newDate.getUTCMonth() + 1, year: newDate.getUTCFullYear() })
    }
    nextTick(() => {
      const cell = document.querySelector(`[data-date="${focusedDate.value}"]`) as HTMLElement
      cell?.focus()
    })
  }
}

function selectDate(date: string) {
  focusedDate.value = date
  emit('select-date', date)
}

watch(() => props.selectedDate, (val) => {
  focusedDate.value = val
})
</script>

<template>
  <div class="calendar-grid">
    <!-- Navigation header -->
    <div class="calendar-grid__nav">
      <button
        class="calendar-grid__nav-btn"
        :aria-label="view === 'week' ? 'Previous week' : 'Previous month'"
        @click="navigatePrev"
      >
        &larr;
      </button>
      <h2 class="calendar-grid__title headline-sm">{{ monthTitle }}</h2>
      <button
        class="calendar-grid__nav-btn"
        :aria-label="view === 'week' ? 'Next week' : 'Next month'"
        @click="navigateNext"
      >
        &rarr;
      </button>
    </div>

    <!-- Month view -->
    <div
      v-if="view === 'month'"
      role="grid"
      aria-label="Calendar"
      class="calendar-grid__month"
      @keydown="handleKeydown"
    >
      <div class="calendar-grid__weekdays" role="row">
        <span
          v-for="day in WEEKDAYS"
          :key="day"
          role="columnheader"
          class="calendar-grid__weekday label-md"
        >
          {{ day }}
        </span>
      </div>
      <div class="calendar-grid__days">
        <CalendarDayCell
          v-for="day in monthDays"
          :key="day.date"
          :data-date="day.date"
          :date="day.date"
          :events="eventsByDate.get(day.date) || []"
          :selected="day.date === selectedDate"
          :today="day.date === todayStr"
          :outside-month="day.outsideMonth"
          :tabindex="day.date === focusedDate ? 0 : -1"
          @select="selectDate"
        />
      </div>
    </div>

    <!-- Week view -->
    <div
      v-else-if="view === 'week'"
      role="grid"
      aria-label="Calendar week"
      class="calendar-grid__week"
      @keydown="handleKeydown"
    >
      <div class="calendar-grid__weekdays" role="row">
        <span
          v-for="day in WEEKDAYS"
          :key="day"
          role="columnheader"
          class="calendar-grid__weekday label-md"
        >
          {{ day }}
        </span>
      </div>
      <div class="calendar-grid__week-days">
        <CalendarDayCell
          v-for="day in weekDays"
          :key="day.date"
          :data-date="day.date"
          :date="day.date"
          :events="eventsByDate.get(day.date) || []"
          :selected="day.date === selectedDate"
          :today="day.date === todayStr"
          :outside-month="day.outsideMonth"
          :tabindex="day.date === focusedDate ? 0 : -1"
          @select="selectDate"
        />
      </div>
    </div>

    <!-- List view -->
    <div v-else-if="view === 'list'" class="calendar-grid__list">
      <template v-if="listEventsByDate.length === 0">
        <p class="calendar-grid__empty body-md">No events this month</p>
      </template>
      <div
        v-for="group in listEventsByDate"
        :key="group.date"
        class="calendar-grid__list-group"
      >
        <h3 class="calendar-grid__list-date title-md">{{ formatWeekdayDate(group.date) }}</h3>
        <ul class="calendar-grid__list-items">
          <li
            v-for="event in group.events"
            :key="event.id"
            class="calendar-grid__list-item"
          >
            <span class="calendar-grid__list-time label-md">
              {{ formatTime(event.startTime) }}
            </span>
            <span class="calendar-grid__list-title body-md">{{ event.title }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
.calendar-grid {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.calendar-grid__nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
}

.calendar-grid__nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  background-color: var(--surface-container-high);
  color: var(--on-surface);
  border: none;
  border-radius: 0.125rem;
  cursor: pointer;
  font-size: var(--type-body-lg);
  transition: background-color var(--duration-micro) var(--ease-standard);
}

.calendar-grid__nav-btn:hover {
  background-color: var(--primary-container);
  color: var(--primary);
}

.calendar-grid__nav-btn:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.calendar-grid__title {
  color: var(--on-surface);
  margin: 0;
  text-align: center;
}

.calendar-grid__weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: var(--space-2xs);
}

.calendar-grid__weekday {
  color: var(--tertiary);
  text-align: center;
  padding: var(--space-xs);
}

.calendar-grid__month .calendar-grid__days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: var(--space-2xs);
}

.calendar-grid__week-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: var(--space-2xs);
}

/* List view */
.calendar-grid__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.calendar-grid__empty {
  color: var(--tertiary);
  margin: 0;
}

.calendar-grid__list-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.calendar-grid__list-date {
  color: var(--secondary);
  margin: 0;
}

.calendar-grid__list-items {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.calendar-grid__list-item {
  display: flex;
  gap: var(--space-md);
  padding: var(--space-sm);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
}

.calendar-grid__list-time {
  color: var(--secondary);
  min-width: 5rem;
}

.calendar-grid__list-title {
  color: var(--on-surface);
}
</style>
