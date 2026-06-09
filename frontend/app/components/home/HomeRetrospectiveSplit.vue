<script setup lang="ts">
import { computed, ref } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'
import { useCalendarEvents } from '~/composables/useCalendarEvents'

const { getEvents } = useCalendarEvents()

// Next upcoming special event across the current + next month (mirrors events/index.vue).
const now = new Date()
const currentMonth = now.getMonth() + 1
const currentYear = now.getFullYear()
const nextMonth = currentMonth === 12 ? 1 : currentMonth + 1
const nextYear = currentMonth === 12 ? currentYear + 1 : currentYear

const { data: currentData } = getEvents(currentMonth, currentYear, 'special_event')
const { data: nextData } = getEvents(nextMonth, nextYear, 'special_event')

const todayKey = now.toISOString().slice(0, 10)

const featured = computed<CalendarEvent | null>(() => {
  const all = [
    ...(currentData.value?.data ?? []),
    ...(nextData.value?.data ?? []),
  ].filter((e) => e.date >= todayKey)
  all.sort((a, b) => a.startTime.localeCompare(b.startTime))
  return all[0] ?? null
})

// Title split: first word plain, the remainder italicised ("Kubrick" + "in the grain").
const titleLead = computed(() => featured.value?.title.split(' ')[0] ?? '')
const titleRest = computed(() => (featured.value?.title.split(' ').slice(1).join(' ')) ?? '')
const glyph = computed(() => (featured.value?.title.charAt(0) ?? '').toUpperCase())

function monthLabel(iso: string): string {
  // Date-only → month name; anchor to UTC noon to avoid TZ day-shift (matches formatDate).
  return new Intl.DateTimeFormat('en-US', { month: 'long', timeZone: 'UTC' }).format(
    new Date(`${iso}T12:00:00Z`),
  )
}
const eyebrow = computed(() =>
  featured.value ? `Feature Programme · ${monthLabel(featured.value.date)}` : '',
)

const imgFailed = ref(false)
const showImage = computed(() => Boolean(featured.value?.imageUrl) && !imgFailed.value)

const ctaHref = computed(() =>
  featured.value?.slug ? `/events/${featured.value.slug}` : '/events',
)
</script>

<template>
  <section v-if="featured" class="retro" aria-labelledby="retro-heading">
    <div class="retro__inner">
      <div class="retro__split">
        <div class="retro__media" aria-hidden="true">
          <img
            v-if="showImage"
            :src="featured.imageUrl"
            alt=""
            class="retro__media-img"
            loading="lazy"
            @error="imgFailed = true"
          >
          <div v-if="showImage" class="retro__media-scrim" />
          <div v-else class="retro__glyph">{{ glyph }}</div>
          <div class="retro__media-label">
            <div class="retro__media-sm">Special Event</div>
            <div class="retro__media-big">{{ formatDate(featured.date) }}</div>
          </div>
        </div>

        <div class="retro__body">
          <div class="retro__eyebrow">{{ eyebrow }}</div>
          <h2 id="retro-heading" class="retro__title">
            {{ titleLead }} <em v-if="titleRest">{{ titleRest }}</em>
          </h2>
          <p class="retro__copy">{{ featured.description }}</p>

          <p class="retro__when">Doors {{ formatWireTime(featured.startTime) }}</p>

          <CvButton variant="primary" :href="ctaHref">
            View Event Details
            <template #icon-right>
              <span aria-hidden="true">&rarr;</span>
            </template>
          </CvButton>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.retro {
  padding-block: var(--space-4xl);
  padding-inline: var(--space-md);
}

@media (min-width: 40rem) {
  .retro { padding-inline: var(--space-xl); }
}

@media (min-width: 60rem) {
  .retro { padding-inline: var(--space-2xl); }
}

.retro__inner {
  max-width: 90rem;
  margin-inline: auto;
}

.retro__split {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-2xl);
  align-items: center;
}

@media (min-width: 60rem) {
  .retro__split {
    grid-template-columns: 5fr 7fr;
    gap: var(--space-3xl);
  }
}

/* ——— Media panel with large K glyph ——— */
.retro__media {
  position: relative;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background: linear-gradient(180deg, #1a0a0a, var(--surface-container-lowest));
  isolation: isolate;
}

.retro__media::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at 30% 40%, rgb(85 0 0 / 0.6), transparent 60%);
  z-index: 0;
}

.retro__media::after {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(0deg, transparent 0 0.125rem, rgb(255 255 255 / 0.015) 0.125rem 0.1875rem);
  mix-blend-mode: overlay;
  z-index: 1;
}

.retro__glyph {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  z-index: 1;
  font-family: var(--font-display);
  font-size: clamp(8rem, 18vw, 16rem);
  color: var(--primary-container);
  line-height: 1;
  letter-spacing: -0.04em;
  font-style: italic;
  opacity: 0.7;
}

.retro__media-label {
  position: absolute;
  bottom: var(--space-md);
  left: var(--space-md);
  right: var(--space-md);
  z-index: 2;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-md);
}

.retro__media-sm {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.retro__media-big {
  font-family: var(--font-display);
  font-size: 2rem;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  text-align: right;
  line-height: 1;
}

/* ——— Body ——— */
.retro__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.retro__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.retro__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.5rem);
  line-height: 1;
  letter-spacing: -0.025em;
  color: var(--on-surface);
  margin: 0 0 var(--space-md);
  text-wrap: balance;
}

.retro__title em {
  font-style: italic;
  color: var(--tertiary);
}

.retro__copy {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1.125rem;
  line-height: 1.55;
  max-width: 48ch;
  margin: 0 0 var(--space-xl);
  text-wrap: pretty;
}

/* Showtime line (replaces the old hardcoded schedule list) */
.retro__when {
  font-family: var(--font-display);
  color: var(--secondary);
  font-size: 1rem;
  letter-spacing: 0.08em;
  margin: 0 0 var(--space-xl);
}

/* API banner image layered over the glyph fallback */
.retro__media-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 1;
}

.retro__media-scrim {
  position: absolute;
  inset: 0;
  z-index: 1;
  background: linear-gradient(to top, rgb(0 0 0 / 0.75), transparent 55%);
}
</style>
