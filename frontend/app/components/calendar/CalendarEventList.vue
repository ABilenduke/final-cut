<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'

defineProps<{
  events: CalendarEvent[]
  date: string
}>()

const typeOrder = ['showtime', 'special_event', 'loyalty_exclusive', 'private_screening_blackout'] as const

function groupByType(events: CalendarEvent[]) {
  const groups: Record<string, CalendarEvent[]> = {}
  for (const event of events) {
    if (!groups[event.type]) groups[event.type] = []
    groups[event.type].push(event)
  }
  return typeOrder
    .filter(t => groups[t]?.length)
    .map(t => ({ type: t, events: groups[t] }))
}

function typeLabel(type: string): string {
  switch (type) {
    case 'showtime': return 'Showtimes'
    case 'special_event': return 'Special Events'
    case 'loyalty_exclusive': return 'Loyalty Exclusives'
    case 'private_screening_blackout': return 'Private Screenings'
    default: return type
  }
}

function eventLink(event: CalendarEvent): string | null {
  if (event.ticketUrl) return event.ticketUrl
  if (event.type === 'special_event' && event.slug) return `/events/${event.slug}`
  if (event.type === 'showtime' && event.movieSlug) return `/movies/${event.movieSlug}`
  return null
}

const accessibilityLabels: Record<string, string> = {
  sensory_friendly: 'Sensory Friendly',
  open_caption: 'Open Captions',
  audio_described: 'Audio Described',
}
</script>

<template>
  <div class="calendar-event-list">
    <h3 class="calendar-event-list__heading headline-sm">
      {{ formatDate(date) }}
    </h3>

    <template v-if="events.length === 0">
      <p class="calendar-event-list__empty body-md">No events on this day</p>
    </template>

    <template v-else>
      <div
        v-for="group in groupByType(events)"
        :key="group.type"
        class="calendar-event-list__group"
      >
        <h4 class="calendar-event-list__group-heading label-lg">
          {{ typeLabel(group.type) }}
        </h4>
        <ul class="calendar-event-list__items">
          <li
            v-for="event in group.events"
            :key="event.id"
            class="calendar-event-list__item"
          >
            <span class="calendar-event-list__time label-md">
              <time :datetime="event.startTime">
                {{ formatTime(event.startTime) }}
              </time>
            </span>
            <div class="calendar-event-list__info">
              <NuxtLink
                v-if="eventLink(event)"
                :to="eventLink(event)!"
                class="calendar-event-list__title title-md"
              >
                {{ event.title }}
              </NuxtLink>
              <span v-else class="calendar-event-list__title title-md">
                {{ event.title }}
              </span>
              <div class="calendar-event-list__badges">
                <CvBadge size="sm">{{ typeLabel(event.type) }}</CvBadge>
                <CvBadge
                  v-if="event.loyaltyOnly"
                  variant="accent"
                  size="sm"
                >
                  Members Only
                </CvBadge>
                <CvBadge
                  v-for="tag in event.accessibilityTags"
                  :key="tag"
                  variant="warning"
                  size="sm"
                >
                  {{ accessibilityLabels[tag] || tag }}
                </CvBadge>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>

<style scoped>
.calendar-event-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.calendar-event-list__heading {
  color: var(--on-surface);
  margin: 0;
}

.calendar-event-list__empty {
  color: var(--tertiary);
  margin: 0;
}

.calendar-event-list__group {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.calendar-event-list__group-heading {
  color: var(--tertiary);
  margin: 0;
}

.calendar-event-list__items {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.calendar-event-list__item {
  display: flex;
  gap: var(--space-md);
  align-items: flex-start;
  padding: var(--space-sm);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
}

.calendar-event-list__time {
  color: var(--secondary);
  white-space: nowrap;
  min-width: 5rem;
}

.calendar-event-list__info {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.calendar-event-list__title {
  color: var(--on-surface);
  text-decoration: none;
}

a.calendar-event-list__title:hover {
  color: var(--secondary);
}

.calendar-event-list__badges {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
}
</style>
