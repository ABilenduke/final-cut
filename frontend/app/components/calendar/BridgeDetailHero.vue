<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarEvent, CalendarEventShowtime } from '~/types/calendar-event'
import { formatWireTime } from '~/utils/formatDate'

const props = defineProps<{
  selectedDate: string
  events: CalendarEvent[]
  heroEvent: CalendarEvent | null
}>()

interface DetailDay {
  num: number
  weekday: string
  monthLabel: string
  year: number
}

function parseLocalDate(date: string): Date {
  const [yStr, mStr, dStr] = date.split('-')
  return new Date(Number(yStr), Number(mStr) - 1, Number(dStr))
}

const detailDay = computed<DetailDay>(() => {
  const d = parseLocalDate(props.selectedDate)
  const monthLabel = d.toLocaleDateString('en-US', { month: 'long' })
  const weekday = d.toLocaleDateString('en-US', { weekday: 'long' })
  return {
    num: d.getDate(),
    weekday,
    monthLabel,
    year: d.getFullYear(),
  }
})

const showtimeTiles = computed<CalendarEventShowtime[]>(() => {
  if (!props.heroEvent || props.heroEvent.type !== 'showtime') return []
  return props.heroEvent.showtimes ?? []
})

const visibleShowtimes = computed(() => showtimeTiles.value.slice(0, 4))

const screeningCount = computed(() => props.events.length)

function shortAuditoriumLabel(label: string): string {
  // Compact "Screen 03"-style names — strip the leading word so the tile
  // label reads "03" / "Aud 3" depending on the source. Auditorium 03 →
  // "Aud 03", Screen 1 → "Screen 1", IMAX → "IMAX".
  const m = label.match(/^(?:Auditorium|Aud)\s+(.+)$/i)
  if (m) return `Aud ${m[1]}`
  return label
}
</script>

<template>
  <article class="bridge-hero-card" aria-labelledby="bridge-hero-day">
    <div class="bridge-hero-card__eyebrow">
      <span>Selected Date</span>
      <b>Live programme</b>
    </div>

    <div class="bridge-hero-card__day">
      <div id="bridge-hero-day" class="bridge-hero-card__day-num">{{ detailDay.num }}</div>
      <div class="bridge-hero-card__day-meta">
        <span>{{ detailDay.monthLabel }} · {{ detailDay.year }}</span>
        <b>{{ detailDay.weekday }}</b>
        <span class="bridge-hero-card__day-count">
          {{ screeningCount }} {{ screeningCount === 1 ? 'screening' : 'screenings' }}
        </span>
      </div>
    </div>

    <div v-if="heroEvent" class="bridge-hero-card__hero">
      <div class="bridge-hero-card__poster">
        <BridgeMiniPoster
          :title="heroEvent.title"
          :poster-url="heroEvent.imageUrl"
          size="md"
        />
      </div>
      <div class="bridge-hero-card__meta">
        <NuxtLink
          v-if="heroEvent.movieSlug"
          :to="`/movies/${heroEvent.movieSlug}`"
          class="bridge-hero-card__title"
        >
          {{ heroEvent.title }}
        </NuxtLink>
        <h2 v-else class="bridge-hero-card__title">{{ heroEvent.title }}</h2>

        <p v-if="heroEvent.description" class="bridge-hero-card__sub">
          {{ heroEvent.description }}
        </p>

        <div v-if="visibleShowtimes.length" class="bridge-hero-card__times">
          <NuxtLink
            v-for="tile in visibleShowtimes"
            :key="tile.id"
            :to="heroEvent.movieSlug ? `/movies/${heroEvent.movieSlug}` : '#'"
            class="bridge-hero-card__time"
            :class="{ 'bridge-hero-card__time--soldout': tile.soldOut }"
            :aria-disabled="tile.soldOut || undefined"
            :tabindex="tile.soldOut ? -1 : undefined"
          >
            <span class="bridge-hero-card__time-t">{{ formatWireTime(tile.startTime) }}</span>
            <span class="bridge-hero-card__time-a">{{ shortAuditoriumLabel(tile.auditoriumLabel) }}</span>
          </NuxtLink>
        </div>

        <CvButton
          v-if="heroEvent.ticketUrl"
          variant="primary"
          size="sm"
          class="bridge-hero-card__cta"
          :href="heroEvent.ticketUrl"
        >
          Get Tickets
        </CvButton>
      </div>
    </div>

    <p v-else class="bridge-hero-card__empty">No film selected for this day.</p>
  </article>
</template>

<style scoped>
.bridge-hero-card {
  background-color: var(--surface-container);
  border-radius: var(--radius-card);
  padding: var(--space-lg);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  position: relative;
}

.bridge-hero-card__eyebrow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.bridge-hero-card__eyebrow b {
  color: var(--secondary);
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.bridge-hero-card__eyebrow b::before {
  content: '';
  width: 0.375rem;
  height: 0.375rem;
  background-color: var(--secondary);
  border-radius: 50%;
  animation: bridge-hero-pulse 1.6s ease-in-out infinite;
}

@keyframes bridge-hero-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

@media (prefers-reduced-motion: reduce) {
  .bridge-hero-card__eyebrow b::before {
    animation: none;
  }
}

.bridge-hero-card__day {
  display: flex;
  align-items: baseline;
  gap: var(--space-md);
  border-bottom: var(--border-hairline) solid rgba(87, 66, 62, 0.2);
  padding-bottom: var(--space-md);
}

.bridge-hero-card__day-num {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: 4rem;
  line-height: 0.85;
  letter-spacing: -0.04em;
  color: var(--on-surface);
  font-variant-numeric: tabular-nums;
}

.bridge-hero-card__day-meta {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.bridge-hero-card__day-meta b {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: var(--type-body-md);
  color: var(--on-surface);
  letter-spacing: -0.01em;
  text-transform: none;
}

.bridge-hero-card__day-count {
  color: var(--secondary);
}

.bridge-hero-card__hero {
  display: grid;
  grid-template-columns: 4rem 1fr;
  gap: var(--space-md);
  align-items: flex-start;
}

.bridge-hero-card__poster {
  width: 4rem;
  height: 6rem;
}

.bridge-hero-card__meta {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-width: 0;
}

.bridge-hero-card__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: 1.375rem;
  line-height: 1.05;
  letter-spacing: -0.015em;
  color: var(--on-surface);
  text-decoration: none;
  margin: 0;
}

.bridge-hero-card__title:hover {
  color: var(--secondary);
}

.bridge-hero-card__sub {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.4;
}

.bridge-hero-card__times {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.375rem;
  margin-top: 0.25rem;
}

.bridge-hero-card__time {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.5rem 0.25rem;
  background-color: var(--surface-container-low);
  border-radius: var(--radius-sm);
  text-decoration: none;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    transform var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.bridge-hero-card__time:hover {
  background-color: var(--primary-container);
  transform: translateY(-1px);
}

.bridge-hero-card__time:hover .bridge-hero-card__time-t {
  color: var(--primary);
}

.bridge-hero-card__time-t {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: 0.875rem;
  letter-spacing: -0.01em;
  color: var(--on-surface);
}

.bridge-hero-card__time-a {
  font-size: 0.5rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: 0.0625rem;
}

.bridge-hero-card__time--soldout {
  opacity: 0.4;
  pointer-events: none;
}

.bridge-hero-card__time--soldout .bridge-hero-card__time-t {
  text-decoration: line-through;
}

.bridge-hero-card__cta {
  align-self: flex-start;
  margin-top: 0.5rem;
}

.bridge-hero-card__empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--on-tertiary-fixed-variant);
}
</style>
