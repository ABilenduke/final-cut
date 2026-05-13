<script setup lang="ts">
import type { Showtime } from '~/types/showtime'
import type { BookingSeat } from '~/types/booking'

const props = defineProps<{
  showtime: Showtime
  seats: readonly BookingSeat[]
  changeSeatsHref?: string | null
}>()

/**
 * Render the movie title with anything after the first colon rendered in italic-accent.
 * "Dune: Part Three" → ["Dune", ": Part Three"].
 */
const titleParts = computed<{ main: string; accent: string | null }>(() => {
  const title = props.showtime.movieTitle
  const idx = title.indexOf(':')
  if (idx === -1) return { main: title, accent: null }
  return {
    main: title.slice(0, idx).trim(),
    accent: `: ${title.slice(idx + 1).trim()}`,
  }
})

const posterGlyph = computed<string>(() => {
  const first = props.showtime.movieTitle.trim().charAt(0).toUpperCase()
  return first || '◉'
})

const formattedDate = computed<string>(() => formatWeekdayDate(props.showtime.startTime))

const formattedTimeParts = computed<{ time: string; period: string }>(() => {
  const raw = formatTime(props.showtime.startTime)
  const match = raw.match(/^([\d:]+)\s*([AP]M)$/i)
  if (!match) return { time: raw, period: '' }
  return { time: match[1] ?? raw, period: match[2] ?? '' }
})

const runtimeLabel = computed<string>(() => {
  const start = new Date(props.showtime.startTime).getTime()
  const end = new Date(props.showtime.endTime).getTime()
  if (Number.isNaN(start) || Number.isNaN(end) || end <= start) return '—'
  return formatRuntime(Math.round((end - start) / 60000))
})

const doorsLabel = computed<string>(() => {
  const start = new Date(props.showtime.startTime)
  if (Number.isNaN(start.getTime())) return '—'
  const doors = new Date(start.getTime() - 30 * 60 * 1000)
  return formatTime(doors.toISOString())
})

const seatCallout = computed<string>(() => {
  if (props.seats.length === 0) return '—'
  // seat.label is the human format "A12" — format as "A·12" for the headline.
  return props.seats.map(s => s.label.replace(/([A-Z]+)(\d+)/, '$1·$2')).join(' / ')
})

const seatSummary = computed<string>(() => {
  if (props.seats.length === 0) return ''
  const firstRow = props.seats[0]?.label.match(/^([A-Z]+)/)?.[1] ?? ''
  const section = props.seats[0]?.section ?? ''
  return section ? `Row ${firstRow} · ${section}` : `Row ${firstRow}`
})
</script>

<template>
  <article class="order-card">
    <div class="order-card__poster" aria-hidden="true">
      <span class="order-card__glyph">{{ posterGlyph }}</span>
    </div>

    <div class="order-card__main">
      <span class="order-card__tag">Now showing · {{ showtime.screenName }}</span>
      <h3 class="order-card__title">
        {{ titleParts.main }}<em v-if="titleParts.accent">{{ titleParts.accent }}</em>
      </h3>
      <dl class="order-card__bullets">
        <div>
          <dt>Date</dt>
          <dd>{{ formattedDate }}</dd>
        </div>
        <div>
          <dt>Time</dt>
          <dd>
            {{ formattedTimeParts.time }}<em v-if="formattedTimeParts.period"> {{ formattedTimeParts.period }}</em>
          </dd>
        </div>
        <div>
          <dt>Auditorium</dt>
          <dd>{{ showtime.screenName }}</dd>
        </div>
        <div>
          <dt>Runtime</dt>
          <dd>{{ runtimeLabel }}</dd>
        </div>
        <div>
          <dt>Doors</dt>
          <dd>{{ doorsLabel }}</dd>
        </div>
        <div>
          <dt>Seats</dt>
          <dd>{{ seats.length }} selected</dd>
        </div>
      </dl>
    </div>

    <div class="order-card__seats">
      <span class="order-card__seats-label">Seats</span>
      <span class="order-card__seats-num">{{ seatCallout }}</span>
      <span v-if="seatSummary" class="order-card__seats-sub">{{ seatSummary }}</span>
      <NuxtLink
        v-if="changeSeatsHref"
        :to="changeSeatsHref"
        class="order-card__change"
      >
        Change seats →
      </NuxtLink>
    </div>
  </article>
</template>

<style scoped>
.order-card {
  display: grid;
  grid-template-columns: 8.75rem 1fr auto;
  gap: var(--space-lg);
  background-color: var(--surface-container-low);
  border-radius: var(--radius-card);
  padding: var(--space-lg);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.15);
  position: relative;
  overflow: hidden;
}

.order-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(ellipse 60% 80% at 85% 20%, rgba(196, 112, 64, 0.1), transparent 60%),
    radial-gradient(ellipse 40% 40% at 20% 80%, rgba(85, 0, 0, 0.15), transparent 60%);
  pointer-events: none;
}

.order-card__poster {
  aspect-ratio: 2 / 3;
  border-radius: var(--radius-sm);
  overflow: hidden;
  position: relative;
  background: radial-gradient(ellipse at 30% 30%, #c47040, #6b2818 55%, #1f0d08 100%);
  box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.6);
  z-index: 1;
}

.order-card__poster::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.07) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
}

.order-card__glyph {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 4.5rem;
  line-height: 0.8;
  color: rgba(255, 255, 255, 0.12);
  letter-spacing: -0.05em;
}

.order-card__main {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  z-index: 1;
  min-width: 0;
}

.order-card__tag {
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--secondary);
}

.order-card__title {
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 500;
  letter-spacing: -0.02em;
  line-height: 1;
  margin: 0;
  color: var(--on-surface);
}

.order-card__title em {
  font-style: italic;
  color: var(--tertiary);
}

.order-card__bullets {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-md) var(--space-lg);
  margin: 0.4rem 0 0;
}

.order-card__bullets div {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.order-card__bullets dt {
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.order-card__bullets dd {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--on-surface);
  font-weight: 500;
  letter-spacing: -0.005em;
  font-variant-numeric: tabular-nums;
  margin: 0;
}

.order-card__bullets dd em {
  font-style: italic;
  color: var(--secondary);
  font-weight: inherit;
}

.order-card__seats {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  align-items: flex-end;
  z-index: 1;
  padding-left: var(--space-md);
  border-left: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.3);
}

.order-card__seats-label {
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.order-card__seats-num {
  font-family: var(--font-display);
  font-size: 2.25rem;
  font-weight: 500;
  letter-spacing: -0.02em;
  line-height: 1;
  color: var(--secondary);
  font-variant-numeric: tabular-nums;
}

.order-card__seats-sub {
  font-family: var(--font-display);
  font-size: 1rem;
  color: var(--on-surface);
  letter-spacing: 0.02em;
}

.order-card__change {
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--tertiary);
  margin-top: 0.3rem;
  text-decoration: none;
}

.order-card__change:hover,
.order-card__change:focus-visible {
  color: var(--secondary);
}

@media (max-width: 68.75rem) {
  .order-card {
    grid-template-columns: 6.875rem 1fr;
  }

  .order-card__seats {
    grid-column: 1 / -1;
    align-items: flex-start;
    padding-left: 0;
    border-left: none;
    border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.3);
    padding-top: var(--space-md);
    flex-direction: row;
    gap: var(--space-lg);
    flex-wrap: wrap;
  }
}
</style>
