<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'

const { getEvents } = useCalendarEvents()
const range = weekRange(new Date())

// Fetch all needed months (1 or 2 when the week spans a month boundary)
const fetches = range.months.map(m => getEvents(m.month, m.year))

// Combine results into a single computed list of this week's events
const weekEvents = computed<CalendarEvent[]>(() => {
  const allEvents: CalendarEvent[] = []
  for (const { data } of fetches) {
    if (data.value?.data) {
      allEvents.push(...data.value.data)
    }
  }

  // Filter to this week's range and qualifying types
  const validTypes = new Set(['special_event', 'loyalty_exclusive'])
  return allEvents
    .filter((e) => {
      if (!validTypes.has(e.type)) return false
      // Noon anchor to avoid timezone-shift issues (same pattern as formatDate utility)
      const eventDate = new Date(e.date + 'T12:00:00')
      return eventDate >= range.start && eventDate < range.end
    })
    .sort((a, b) => a.date.localeCompare(b.date))
    .slice(0, 5)
})

const hasEvents = computed(() => weekEvents.value.length > 0)

// Format day + date (e.g., "Thu, Apr 9")
const dayDateFormatter = new Intl.DateTimeFormat('en-US', {
  weekday: 'short',
  month: 'short',
  day: 'numeric',
})

function formatDayDate(dateStr: string): string {
  return dayDateFormatter.format(new Date(dateStr + 'T12:00:00'))
}

function truncate(text: string, max: number): string {
  if (text.length <= max) return text
  return text.slice(0, max).trimEnd() + '\u2026'
}

function eventLink(event: CalendarEvent): string {
  if (event.type === 'showtime' && event.movieSlug) {
    return `/movies/${event.movieSlug}`
  }
  if (event.slug) {
    return `/events/${event.slug}`
  }
  return '/whats-on'
}
</script>

<template>
  <section v-if="hasEvents" class="event-strip">
    <div class="event-strip__container">
      <h2 class="event-strip__heading headline-md">What's On This Week</h2>

      <div class="event-strip__list">
        <div v-for="event in weekEvents" :key="event.id" class="event-strip__item">
          <span class="event-strip__date label-lg">{{ formatDayDate(event.date) }}</span>
          <div class="event-strip__info">
            <span class="event-strip__title title-md">{{ event.title }}</span>
            <span class="event-strip__desc body-sm">{{ truncate(event.description, 80) }}</span>
          </div>
          <CvButton variant="tertiary" size="sm" :href="eventLink(event)">
            View
          </CvButton>
        </div>
      </div>

      <div class="event-strip__footer">
        <CvButton variant="tertiary" href="/whats-on">View full calendar</CvButton>
      </div>
    </div>
  </section>
</template>

<style scoped>
.event-strip {
  background-color: var(--surface-container-lowest);
  padding-block: var(--space-3xl);
}

.event-strip__container {
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-2xl);
}

.event-strip__heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-lg);
}

.event-strip__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.event-strip__item {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.event-strip__date {
  color: var(--secondary);
  min-width: 6rem;
  flex-shrink: 0;
}

.event-strip__info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
  min-width: 0;
}

.event-strip__title {
  color: var(--on-surface);
}

.event-strip__desc {
  color: var(--tertiary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.event-strip__footer {
  margin-top: var(--space-lg);
}

@media (max-width: 60rem) {
  .event-strip__container {
    padding-inline: var(--space-md);
  }

  .event-strip__item {
    flex-wrap: wrap;
  }

  .event-strip__date {
    min-width: auto;
  }
}
</style>
