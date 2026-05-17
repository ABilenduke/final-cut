<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'
import { FC_TYPE_COLORS, chipSlugForEvent } from '~/composables/useBridgeFilters'
import { formatWireTime } from '~/utils/formatDate'

const props = withDefaults(defineProps<{
  date: string
  dayNumber: number
  events: CalendarEvent[]
  selected: boolean
  today: boolean
  outsideMonth: boolean
  focused: boolean
}>(), {})

const emit = defineEmits<{
  select: [date: string]
}>()

const flagColors = computed(() => {
  const seen = new Set<string>()
  const colors: string[] = []
  for (const ev of props.events) {
    const slug = chipSlugForEvent(ev)
    if (!seen.has(slug)) {
      seen.add(slug)
      colors.push(FC_TYPE_COLORS[slug])
      if (colors.length >= 4) break
    }
  }
  return colors
})

const visibleEvents = computed(() => props.events.slice(0, 2))
const overflowCount = computed(() => Math.max(0, props.events.length - 2))

const hasRental = computed(() =>
  props.events.some((e) => e.type === 'private_screening_blackout'),
)

function eventLineLabel(event: CalendarEvent): string {
  if (event.type === 'private_screening_blackout') return 'Privately booked'
  return event.title
}

function onClick() {
  if (props.outsideMonth) return
  emit('select', props.date)
}

function onKeydown(event: KeyboardEvent) {
  if (props.outsideMonth) return
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    emit('select', props.date)
  }
}

const ariaLabel = computed(() => {
  const parts = [props.date]
  parts.push(`${props.events.length} event${props.events.length === 1 ? '' : 's'}`)
  if (props.today) parts.push('today')
  if (props.selected) parts.push('selected')
  return parts.join(', ')
})
</script>

<template>
  <div
    class="bridge-day-cell"
    :class="{
      'bridge-day-cell--muted': outsideMonth,
      'bridge-day-cell--today': today,
      'bridge-day-cell--selected': selected,
      'bridge-day-cell--has-rental': hasRental,
    }"
    role="gridcell"
    :aria-selected="selected"
    :aria-disabled="outsideMonth || undefined"
    :aria-label="ariaLabel"
    :tabindex="outsideMonth ? -1 : (focused ? 0 : -1)"
    @click="onClick"
    @keydown="onKeydown"
  >
    <div class="bridge-day-cell__head">
      <span class="bridge-day-cell__num">{{ dayNumber }}</span>
      <span v-if="flagColors.length" class="bridge-day-cell__flags" aria-hidden="true">
        <span
          v-for="(color, i) in flagColors"
          :key="i"
          class="bridge-day-cell__flag"
          :style="{ '--bridge-flag-color': color }"
        />
      </span>
    </div>

    <div v-if="visibleEvents.length" class="bridge-day-cell__events">
      <div
        v-for="event in visibleEvents"
        :key="event.id"
        class="bridge-day-cell__event"
        :style="{ '--bridge-event-color': FC_TYPE_COLORS[chipSlugForEvent(event)] }"
      >
        <span class="bridge-day-cell__event-time">{{ formatWireTime(event.startTime) }}</span>
        <span class="bridge-day-cell__event-title">{{ eventLineLabel(event) }}</span>
      </div>
      <div v-if="overflowCount" class="bridge-day-cell__overflow">
        +{{ overflowCount }} more
      </div>
    </div>
  </div>
</template>

<style scoped>
.bridge-day-cell {
  background-color: var(--surface-container-lowest);
  padding: var(--space-sm);
  aspect-ratio: 1 / 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  cursor: pointer;
  position: relative;
  transition: background-color var(--duration-standard) var(--ease-standard);
}

.bridge-day-cell:hover:not(.bridge-day-cell--muted) {
  background-color: var(--surface-container-low);
}

.bridge-day-cell--muted {
  background-color: var(--surface);
  cursor: default;
  opacity: 0.55;
}

.bridge-day-cell--muted .bridge-day-cell__num {
  color: var(--outline-variant);
}

.bridge-day-cell--today {
  background-color: var(--surface-container);
}

.bridge-day-cell--today .bridge-day-cell__num {
  color: var(--secondary);
  font-weight: 600;
}

.bridge-day-cell--selected {
  background-color: var(--primary-container);
  outline: var(--border-hairline) solid rgba(218, 199, 105, 0.4);
  outline-offset: -1px;
  z-index: var(--z-card);
}

.bridge-day-cell--selected .bridge-day-cell__num {
  color: var(--primary);
}

.bridge-day-cell--selected .bridge-day-cell__event {
  color: var(--primary);
  background-color: rgba(0, 0, 0, 0.25);
}

.bridge-day-cell--has-rental {
  background-image: linear-gradient(
    135deg,
    transparent 0 calc(100% - 1.5rem),
    rgba(87, 66, 62, 0.5) calc(100% - 1.5rem)
  );
}

.bridge-day-cell:focus-visible {
  outline: var(--border-hairline) solid var(--secondary);
  outline-offset: -1px;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    inset 0 0 0 0.125rem var(--secondary);
}

.bridge-day-cell__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.bridge-day-cell__num {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: var(--type-body-md);
  letter-spacing: -0.01em;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  color: var(--on-surface);
}

.bridge-day-cell__flags {
  display: inline-flex;
  gap: 0.1875rem;
}

.bridge-day-cell__flag {
  width: 0.3125rem;
  height: 0.3125rem;
  border-radius: 50%;
  background-color: var(--bridge-flag-color, var(--tertiary));
}

.bridge-day-cell__events {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  flex: 1;
  min-height: 0;
}

.bridge-day-cell__event {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
  font-size: 0.6875rem;
  color: var(--tertiary);
  line-height: 1.3;
  padding: 0.0625rem 0.1875rem;
  border-left: 0.125rem solid var(--bridge-event-color, transparent);
  background-color: rgba(255, 255, 255, 0.025);
  border-radius: 0 0.0625rem 0.0625rem 0;
}

.bridge-day-cell__event-time {
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
  color: var(--on-surface);
  flex-shrink: 0;
  font-size: 0.6875rem;
}

.bridge-day-cell__event-title {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

.bridge-day-cell__overflow {
  font-size: 0.5625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: auto;
  padding-top: 0.25rem;
}

@media (max-width: 60rem) {
  .bridge-day-cell {
    min-height: 3rem;
  }

  .bridge-day-cell__events,
  .bridge-day-cell__overflow {
    display: none;
  }
}
</style>
