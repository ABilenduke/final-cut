<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'
import { FC_TYPE_COLORS, chipSlugForEvent } from '~/composables/useBridgeFilters'

// List view (admin-v5 Plan 04): the visible month's events as a
// chronological agenda grouped by day. `todayDate` arrives as a prop —
// never derived here (date-hydration rule).
const props = defineProps<{
  events: CalendarEvent[]
  selectedDate: string
  todayDate: string
}>()

const emit = defineEmits<{
  'select-date': [date: string]
}>()

interface AgendaDay {
  key: string
  heading: string
  events: CalendarEvent[]
}

const agendaDays = computed<AgendaDay[]>(() => {
  const byDate = new Map<string, CalendarEvent[]>()
  for (const event of props.events) {
    const list = byDate.get(event.date) ?? []
    list.push(event)
    byDate.set(event.date, list)
  }

  return [...byDate.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([key, events]) => {
      const [y, m, d] = key.split('-').map(Number) as [number, number, number]
      return {
        key,
        heading: new Date(y, m - 1, d).toLocaleDateString('en-US', {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
        }),
        events: events.sort((a, b) => a.startTime.localeCompare(b.startTime)),
      }
    })
})

function timeLabel(event: CalendarEvent): string {
  return event.startTime.slice(11, 16)
}

function eventColor(event: CalendarEvent): string {
  return FC_TYPE_COLORS[chipSlugForEvent(event)]
}
</script>

<template>
  <div class="agenda" aria-label="List view">
    <p v-if="agendaDays.length === 0" class="agenda__empty" data-testid="agenda-empty">
      Nothing scheduled this month under the current filters.
    </p>

    <section
      v-for="day in agendaDays"
      :key="day.key"
      class="agenda__day"
      :class="{
        'agenda__day--today': day.key === todayDate,
        'agenda__day--selected': day.key === selectedDate,
      }"
      data-testid="agenda-day"
    >
      <button
        type="button"
        class="agenda__heading"
        data-testid="agenda-day-heading"
        @click="emit('select-date', day.key)"
      >
        {{ day.heading }}
      </button>

      <ul class="agenda__events">
        <li
          v-for="event in day.events"
          :key="event.id"
          class="agenda__event"
          :style="{ borderLeftColor: eventColor(event) }"
        >
          <span class="agenda__event-time">{{ timeLabel(event) }}</span>
          <span class="agenda__event-title">{{ event.title }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.agenda {
  display: grid;
  gap: var(--space-lg);
  padding: var(--space-md);
  background-color: var(--surface-container-lowest);
  border-radius: var(--radius-sm);
}

.agenda__empty {
  margin: 0;
  padding: var(--space-xl);
  text-align: center;
  color: var(--tertiary);
}

.agenda__day {
  display: grid;
  gap: var(--space-sm);
}

.agenda__day--selected .agenda__heading {
  color: var(--secondary);
}

.agenda__day--today .agenda__heading::after {
  content: ' · Today';
  color: var(--secondary);
  font-size: 0.75rem;
  letter-spacing: 0.08em;
}

.agenda__heading {
  justify-self: start;
  background: none;
  border: none;
  padding: 0;
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--on-surface);
  cursor: pointer;
}

.agenda__events {
  display: grid;
  gap: var(--space-xs);
  margin: 0;
  padding: 0;
  list-style: none;
}

.agenda__event {
  display: flex;
  gap: var(--space-md);
  padding: var(--space-sm) var(--space-md);
  border-left: 2px solid transparent;
  background-color: var(--surface-container-low);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
}

.agenda__event-time {
  color: var(--tertiary);
  min-width: 3rem;
}

.agenda__event-title {
  color: var(--on-surface);
}
</style>
