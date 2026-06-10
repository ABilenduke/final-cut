<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'
import { FC_TYPE_COLORS, chipSlugForEvent } from '~/composables/useBridgeFilters'
import { toLocalDateKey } from '~/utils/formatDate'

// Week view (admin-v5 Plan 04): the Monday-start week containing the
// selected date, one column per day, full event listings (no +N cap).
// `todayDate` arrives as a prop — never derived here (date-hydration rule).
const props = defineProps<{
  events: CalendarEvent[]
  selectedDate: string
  todayDate: string
  month: number
  year: number
}>()

const emit = defineEmits<{
  'select-date': [date: string]
}>()

interface WeekDay {
  key: string
  dayNumber: number
  weekdayLabel: string
  inMonth: boolean
  events: CalendarEvent[]
}

const weekDays = computed<WeekDay[]>(() => {
  const [y, m, d] = props.selectedDate.split('-').map(Number) as [number, number, number]
  const selected = new Date(y, m - 1, d)
  // Monday-start offset: JS getDay() is 0=Sunday.
  const offset = (selected.getDay() + 6) % 7
  const monday = new Date(y, m - 1, d - offset)

  return Array.from({ length: 7 }, (_, i) => {
    const day = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i)
    const key = toLocalDateKey(day.getFullYear(), day.getMonth() + 1, day.getDate())
    return {
      key,
      dayNumber: day.getDate(),
      weekdayLabel: day.toLocaleDateString('en-US', { weekday: 'short' }),
      inMonth: day.getMonth() + 1 === props.month && day.getFullYear() === props.year,
      events: props.events
        .filter((e) => e.date === key)
        .sort((a, b) => a.startTime.localeCompare(b.startTime)),
    }
  })
})

function timeLabel(event: CalendarEvent): string {
  const time = event.startTime.slice(11, 16)
  return time || ''
}

function eventColor(event: CalendarEvent): string {
  return FC_TYPE_COLORS[chipSlugForEvent(event)]
}
</script>

<template>
  <div class="week-strip" role="list" aria-label="Week view">
    <button
      v-for="day in weekDays"
      :key="day.key"
      type="button"
      role="listitem"
      class="week-strip__day"
      :class="{
        'week-strip__day--today': day.key === todayDate,
        'week-strip__day--selected': day.key === selectedDate,
        'week-strip__day--muted': !day.inMonth,
      }"
      data-testid="week-day"
      @click="emit('select-date', day.key)"
    >
      <span class="week-strip__weekday">{{ day.weekdayLabel }}</span>
      <span class="week-strip__number">{{ day.dayNumber }}</span>

      <ul class="week-strip__events">
        <li
          v-for="event in day.events"
          :key="event.id"
          class="week-strip__event"
          :style="{ borderLeftColor: eventColor(event) }"
        >
          <span class="week-strip__event-time">{{ timeLabel(event) }}</span>
          <span class="week-strip__event-title">{{ event.title }}</span>
        </li>
      </ul>
    </button>
  </div>
</template>

<style scoped>
.week-strip {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background-color: var(--surface-container-low);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.week-strip__day {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: var(--space-sm);
  min-height: 14rem;
  padding: var(--space-md);
  background-color: var(--surface-container-lowest);
  border: none;
  text-align: left;
  cursor: pointer;
  color: var(--on-surface);
}

.week-strip__day--today .week-strip__number {
  color: var(--secondary);
}

.week-strip__day--selected {
  background-color: var(--primary-container);
}

.week-strip__day--muted {
  opacity: 0.45;
}

.week-strip__weekday {
  font-size: 0.6875rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--tertiary);
}

.week-strip__number {
  font-family: var(--font-display);
  font-size: 1.5rem;
  line-height: 1;
}

.week-strip__events {
  display: grid;
  gap: var(--space-xs);
  margin: 0;
  padding: 0;
  list-style: none;
}

.week-strip__event {
  display: grid;
  gap: 0.125rem;
  padding-left: var(--space-sm);
  border-left: 2px solid transparent;
  font-size: 0.75rem;
}

.week-strip__event-time {
  color: var(--tertiary);
}

.week-strip__event-title {
  color: var(--on-surface);
}

@media (max-width: 60rem) {
  .week-strip {
    grid-template-columns: 1fr;
  }

  .week-strip__day {
    min-height: 0;
  }
}
</style>
