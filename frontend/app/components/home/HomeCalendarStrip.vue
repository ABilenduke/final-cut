<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'
import { formatTime } from '~/utils/formatDate'
import { weekRange } from '~/utils/weekRange'

// ——— Fetch this week's events (may span a month boundary) ———
const { getEvents } = useCalendarEvents()
const range = weekRange(new Date())
const fetches = range.months.map((m) => getEvents(m.month, m.year))

// Flatten API results into a single list of events for this week.
const weekEvents = computed<CalendarEvent[]>(() => {
  const all: CalendarEvent[] = []
  for (const { data } of fetches) {
    if (data.value?.data) all.push(...data.value.data)
  }
  return all.filter((e) => {
    // Date-only strings need noon anchoring to avoid timezone day-shift
    const d = new Date(`${e.date}T12:00:00`)
    return d >= range.start && d < range.end
  })
})

// ——— Seven day cells, each with up to 2 events + an overflow count ———
interface DayCell {
  iso: string
  dow: string // "Thu"
  dom: string // "17"
  isToday: boolean
  events: Array<{ time: string; title: string; href: string }>
  overflow: number
}

const todayIso = new Date().toISOString().slice(0, 10)
const dowFmt = new Intl.DateTimeFormat('en-US', { weekday: 'short' })

function dayHref(event: CalendarEvent): string {
  if (event.type === 'showtime' && event.movieSlug) return `/movies/${event.movieSlug}`
  if (event.slug) return `/events/${event.slug}`
  return '/whats-on'
}

const cells = computed<DayCell[]>(() => {
  const out: DayCell[] = []
  const d = new Date(range.start)
  for (let i = 0; i < 7; i++) {
    const iso = d.toISOString().slice(0, 10)
    const dayEvents = weekEvents.value
      .filter((e) => e.date === iso)
      .sort((a, b) => a.startTime.localeCompare(b.startTime))

    const visible = dayEvents.slice(0, 2).map((e) => ({
      time: formatTime(e.startTime),
      title: e.title,
      href: dayHref(e),
    }))

    out.push({
      iso,
      dow: dowFmt.format(d),
      dom: String(d.getDate()),
      isToday: iso === todayIso,
      events: visible,
      overflow: Math.max(0, dayEvents.length - visible.length),
    })
    d.setDate(d.getDate() + 1)
  }
  return out
})

// ——— Header range label: "Apr 17 — Apr 23 · Week 16" ———
const monthDayFmt = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' })

function weekOfYear(d: Date): number {
  const target = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
  const dayNum = target.getUTCDay() === 0 ? 7 : target.getUTCDay()
  target.setUTCDate(target.getUTCDate() + 4 - dayNum)
  const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1))
  return Math.ceil(((target.getTime() - yearStart.getTime()) / 86400000 + 1) / 7)
}

const rangeLabel = computed(() => {
  const startStr = monthDayFmt.format(range.start)
  const endDate = new Date(range.end)
  endDate.setDate(endDate.getDate() - 1)
  const endStr = monthDayFmt.format(endDate)
  return `${startStr} \u2014 ${endStr} \u00B7 Week ${weekOfYear(range.start)}`
})
</script>

<template>
  <section class="cal-strip" aria-labelledby="cal-strip-heading">
    <div class="cal-strip__inner">
      <header class="cal-strip__head">
        <div>
          <div class="cal-strip__eyebrow">{{ rangeLabel }}</div>
          <h2 id="cal-strip-heading" class="cal-strip__title">The Week Ahead.</h2>
        </div>
        <NuxtLink to="/whats-on" class="cal-strip__link">Full Calendar &rarr;</NuxtLink>
      </header>

      <div class="cal-strip__grid" role="list">
        <div
          v-for="cell in cells"
          :key="cell.iso"
          role="listitem"
          class="cal-strip__day"
          :class="{ 'cal-strip__day--today': cell.isToday }"
        >
          <div class="cal-strip__day-head">
            <span class="cal-strip__dow">{{ cell.dow }}<span v-if="cell.isToday"> · Today</span></span>
            <span class="cal-strip__date">{{ cell.dom }}</span>
          </div>
          <div class="cal-strip__events">
            <NuxtLink
              v-for="(evt, i) in cell.events"
              :key="i"
              :to="evt.href"
              class="cal-strip__event"
            >
              <span class="cal-strip__event-time">{{ evt.time }}</span>
              <span class="cal-strip__event-title">{{ evt.title }}</span>
            </NuxtLink>
            <span v-if="cell.events.length === 0" class="cal-strip__event cal-strip__event--empty">
              <span>Quiet day</span>
            </span>
            <span v-if="cell.overflow > 0" class="cal-strip__overflow">
              + {{ cell.overflow }} more
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cal-strip {
  background-color: var(--surface-container-lowest);
  padding-block: var(--space-4xl);
  padding-inline: var(--space-md);
}

@media (min-width: 40rem) {
  .cal-strip { padding-inline: var(--space-xl); }
}

@media (min-width: 60rem) {
  .cal-strip { padding-inline: var(--space-2xl); }
}

.cal-strip__inner {
  max-width: 90rem;
  margin-inline: auto;
}

.cal-strip__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-xl);
  margin-bottom: var(--space-2xl);
}

.cal-strip__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.cal-strip__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.cal-strip__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.5rem);
  line-height: 1;
  letter-spacing: -0.025em;
  color: var(--on-surface);
  margin: 0.75rem 0 0;
}

.cal-strip__link {
  display: inline-flex;
  align-items: center;
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: 0.875rem;
  text-decoration: none;
  padding-block: var(--space-xs);
  border-bottom: 0.0625rem solid transparent;
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.cal-strip__link:hover { border-bottom-color: var(--secondary); }

.cal-strip__grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  border-top: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.25); /* token-exception: sub-pixel edge */
  border-bottom: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.25); /* token-exception: sub-pixel edge */
}

@media (min-width: 40rem) {
  .cal-strip__grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (min-width: 60rem) {
  .cal-strip__grid {
    grid-template-columns: repeat(7, 1fr);
  }
}

.cal-strip__day {
  padding: var(--space-lg) var(--space-md);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  border-right: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2); /* token-exception: sub-pixel edge */
  border-bottom: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2); /* token-exception: sub-pixel edge */
  transition: background-color var(--duration-standard) var(--ease-standard);
}

.cal-strip__day:last-child { border-right: none; }
.cal-strip__day:hover { background-color: rgb(85 0 0 / 0.1); }

.cal-strip__day--today {
  background-color: var(--surface-container);
}

.cal-strip__day--today .cal-strip__date {
  color: var(--secondary);
}

.cal-strip__day-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.cal-strip__dow {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.cal-strip__date {
  font-family: var(--font-display);
  font-size: 1.75rem;
  letter-spacing: -0.02em;
  line-height: 1;
  color: var(--on-surface);
}

.cal-strip__events {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  margin-top: 0.25rem;
}

.cal-strip__event {
  display: flex;
  gap: var(--space-sm);
  font-family: var(--font-body);
  font-size: 0.75rem;
  line-height: 1.3;
  color: var(--tertiary);
  text-decoration: none;
  transition: color var(--duration-micro) var(--ease-standard);
}

.cal-strip__event:hover {
  color: var(--on-surface);
}

.cal-strip__event--empty {
  /* Must remain legible on both surface (#0e0e0e) and surface-container
     (#201f1f) backgrounds. Using a dedicated dim token instead of
     opacity keeps contrast at WCAG AA (4.5:1+) on either tier. */
  color: var(--on-tertiary-fixed-variant);
  pointer-events: none;
}

.cal-strip__event-time {
  color: var(--secondary);
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
  min-width: 3rem;
}

.cal-strip__event-title {
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.cal-strip__overflow {
  font-family: var(--font-body);
  color: var(--on-tertiary-fixed-variant);
  font-size: 0.625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  margin-top: 0.25rem;
}
</style>
