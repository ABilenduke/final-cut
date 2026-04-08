<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'

const props = withDefaults(defineProps<{
  date: string
  events: CalendarEvent[]
  selected: boolean
  today: boolean
  outsideMonth?: boolean
}>(), {
  outsideMonth: false,
})

const emit = defineEmits<{
  select: [date: string]
}>()

const dayNumber = computed(() => {
  const d = new Date(`${props.date}T12:00:00Z`)
  return d.getUTCDate()
})

const eventDots = computed(() => {
  const dots: string[] = []
  const types = new Set(props.events.map(e => e.type))
  if (types.has('showtime')) dots.push('showtime')
  if (types.has('special_event')) dots.push('special_event')
  if (types.has('loyalty_exclusive')) dots.push('loyalty_exclusive')
  return dots.slice(0, 3)
})

const hasAccessibilityEvent = computed(() =>
  props.events.some(e => e.accessibilityTags.length > 0),
)

const ariaLabel = computed(() => {
  const d = new Date(`${props.date}T12:00:00Z`)
  const formatted = d.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC',
  })
  const count = props.events.length
  return `${formatted}. ${count} event${count !== 1 ? 's' : ''}.`
})
</script>

<template>
  <div
    role="gridcell"
    :aria-label="ariaLabel"
    :aria-selected="selected || undefined"
    class="calendar-day-cell"
    :class="{
      'calendar-day-cell--selected': selected,
      'calendar-day-cell--today': today,
      'calendar-day-cell--outside': outsideMonth,
    }"
    tabindex="-1"
    @click="emit('select', date)"
    @keydown.enter.prevent="emit('select', date)"
    @keydown.space.prevent="emit('select', date)"
  >
    <span class="calendar-day-cell__number label-lg">{{ dayNumber }}</span>
    <div v-if="eventDots.length > 0" class="calendar-day-cell__dots">
      <span
        v-for="dot in eventDots"
        :key="dot"
        class="calendar-day-cell__dot"
        :class="`calendar-day-cell__dot--${dot}`"
      />
    </div>
    <span
      v-if="hasAccessibilityEvent"
      class="calendar-day-cell__a11y-indicator"
      aria-hidden="true"
    >
      &#9835;
    </span>
  </div>
</template>

<style scoped>
.calendar-day-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: var(--space-2xs);
  min-height: 3rem;
  min-width: 3rem;
  padding: var(--space-xs);
  cursor: pointer;
  border-radius: 0.125rem;
  transition:
    background-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard);
  position: relative;
}

.calendar-day-cell:hover {
  background-color: var(--surface-container-high);
}

.calendar-day-cell__number {
  color: var(--on-surface);
}

.calendar-day-cell--today .calendar-day-cell__number {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}

.calendar-day-cell--selected {
  background-color: var(--primary-container);
}

.calendar-day-cell--selected .calendar-day-cell__number {
  color: var(--primary);
}

.calendar-day-cell--outside {
  opacity: 0.4;
}

.calendar-day-cell:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.calendar-day-cell__dots {
  display: flex;
  gap: var(--space-2xs);
}

.calendar-day-cell__dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
}

.calendar-day-cell__dot--showtime {
  background-color: var(--tertiary);
}

.calendar-day-cell__dot--special_event {
  background-color: var(--secondary);
}

.calendar-day-cell__dot--loyalty_exclusive {
  background-color: var(--primary-container);
}

.calendar-day-cell__a11y-indicator {
  position: absolute;
  top: var(--space-2xs);
  right: var(--space-2xs);
  font-size: var(--type-label-sm);
  color: var(--secondary);
  line-height: 1;
}
</style>
