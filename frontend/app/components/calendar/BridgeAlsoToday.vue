<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'
import {
  EVENT_TYPE_LABELS,
  FC_TYPE_COLORS,
  chipSlugForEvent,
} from '~/composables/useBridgeFilters'
import { formatWireTime } from '~/utils/formatDate'

const props = defineProps<{
  events: CalendarEvent[]
}>()

const visible = computed(() => props.events.slice(0, 5))

function rowHref(event: CalendarEvent): string | null {
  if (event.type === 'private_screening_blackout') return null
  if (event.slug) return `/events/${event.slug}`
  if (event.movieSlug) return `/movies/${event.movieSlug}`
  return null
}
</script>

<template>
  <article v-if="visible.length" class="bridge-also-today">
    <header class="bridge-also-today__head">
      <span>Also Today</span>
      <span>{{ events.length }}</span>
    </header>

    <ul class="bridge-also-today__list">
      <li
        v-for="event in visible"
        :key="event.id"
        class="bridge-also-today__row"
        :class="{ 'bridge-also-today__row--rental': event.type === 'private_screening_blackout' }"
      >
        <component
          :is="rowHref(event) ? 'NuxtLink' : 'div'"
          :to="rowHref(event) ?? undefined"
          class="bridge-also-today__row-link"
          :aria-disabled="event.type === 'private_screening_blackout' || undefined"
        >
          <span class="bridge-also-today__time">
            {{ event.type === 'private_screening_blackout' ? '—' : formatWireTime(event.startTime) }}
          </span>
          <span class="bridge-also-today__body">
            <b v-if="event.type === 'private_screening_blackout'" class="bridge-also-today__title bridge-also-today__title--rental">
              Private rental · all day
            </b>
            <b v-else class="bridge-also-today__title">{{ event.title }}</b>
            <span
              class="bridge-also-today__type"
              :style="{ '--bridge-evt-color': FC_TYPE_COLORS[chipSlugForEvent(event)] }"
            >{{ EVENT_TYPE_LABELS[event.type] }}</span>
          </span>
          <span class="bridge-also-today__badge" aria-hidden="true">
            <CvIcon
              :name="event.type === 'private_screening_blackout' ? 'close' : 'chevron-right'"
              size="sm"
            />
          </span>
        </component>
      </li>
    </ul>
  </article>
</template>

<style scoped>
.bridge-also-today {
  background-color: var(--surface-container);
  border-radius: var(--radius-card);
  padding: var(--space-lg);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.bridge-also-today__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding: 0.5rem 0;
  border-bottom: var(--border-hairline) solid rgba(87, 66, 62, 0.18);
}

.bridge-also-today__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.bridge-also-today__row {
  border-bottom: var(--border-hairline) solid rgba(87, 66, 62, 0.1);
}

.bridge-also-today__row:last-child {
  border-bottom: none;
}

.bridge-also-today__row-link {
  display: grid;
  grid-template-columns: 3rem 1fr auto;
  gap: 0.75rem;
  align-items: center;
  padding: 0.5rem 0;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  transition: padding var(--duration-standard) var(--ease-standard);
}

.bridge-also-today__row-link:hover {
  padding-left: 0.25rem;
}

.bridge-also-today__row--rental .bridge-also-today__row-link {
  cursor: not-allowed;
  opacity: 0.6;
}

.bridge-also-today__row--rental .bridge-also-today__row-link:hover {
  padding-left: 0;
}

.bridge-also-today__time {
  font-family: var(--font-display);
  font-size: 0.875rem;
  font-variant-numeric: tabular-nums;
  color: var(--on-surface);
  letter-spacing: -0.01em;
}

.bridge-also-today__body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.bridge-also-today__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: 0.8125rem;
  color: var(--on-surface);
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bridge-also-today__title--rental {
  color: var(--outline);
}

.bridge-also-today__type {
  font-size: 0.5625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--bridge-evt-color, var(--on-tertiary-fixed-variant));
}

.bridge-also-today__badge {
  width: 1.75rem;
  height: 1.75rem;
  display: grid;
  place-items: center;
  background-color: var(--surface-container-low);
  border-radius: 50%;
  color: var(--on-tertiary-fixed-variant);
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.bridge-also-today__row-link:hover .bridge-also-today__badge {
  background-color: var(--secondary);
  color: var(--surface);
}

.bridge-also-today__row--rental .bridge-also-today__row-link:hover .bridge-also-today__badge {
  background-color: var(--surface-container-low);
  color: var(--on-tertiary-fixed-variant);
}
</style>
