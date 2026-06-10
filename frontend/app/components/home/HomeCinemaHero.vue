<script setup lang="ts">
import type { Movie } from '~/types/movie'
import { formatRuntime } from '~/utils/formatRuntime'
import { useShowtimes } from '~/composables/useShowtimes'
import { buildHeroSlots, type HeroSlot } from '~/utils/buildHeroSlots'

const props = defineProps<{
  movie: Movie
  featureNumber?: string
}>()

// Preload the backdrop so the hero lands fast.
useHead(() => ({
  link: props.movie.backdropUrl
    ? [{ rel: 'preload', as: 'image', href: props.movie.backdropUrl }]
    : [],
}))

// Real upcoming showtimes for the featured movie (admin-v2 Plan 16 —
// replaces the hardcoded placeholder slots). The endpoint already filters
// to the upcoming window and sorts by start time.
const appTimeZone = String(useRuntimeConfig().public.appTimeZone || 'America/New_York')
const { fetchByMovie } = useShowtimes()
const { data: showtimesData } = fetchByMovie(props.movie.slug)

const heroSlots = computed<HeroSlot[]>(() =>
  buildHeroSlots(showtimesData.value?.data ?? [], appTimeZone),
)

// ——— Live telemetry clock ———
const clock = ref('00:00:00')
let clockHandle: number | null = null

function tick() {
  const d = new Date()
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  const ss = String(d.getSeconds()).padStart(2, '0')
  clock.value = `${hh}:${mm}:${ss}`
}

onMounted(() => {
  tick()
  clockHandle = window.setInterval(tick, 1000)
})

onBeforeUnmount(() => {
  if (clockHandle !== null) window.clearInterval(clockHandle)
})

// ——— Derived feature metadata ———
const runtimeDisplay = computed(() => formatRuntime(props.movie.runtime))
const scoreDisplay = computed(() => (props.movie.rating ? Math.round(props.movie.rating * 10) : null))
const featureNum = computed(() => props.featureNumber ?? '01')

const ticketsHref = computed(() => `/movies/${props.movie.slug}`)
const trailerHref = computed(() =>
  props.movie.trailerKey ? `https://www.youtube.com/watch?v=${props.movie.trailerKey}` : null,
)
</script>

<template>
  <section class="cinema-hero" aria-label="Featured film">
    <!-- Layered atmosphere -->
    <div class="cinema-hero__bg" aria-hidden="true" />

    <!-- Backdrop behind the atmosphere, blended for mood -->
    <img
      v-if="movie.backdropUrl"
      :src="movie.backdropUrl"
      alt=""
      aria-hidden="true"
      class="cinema-hero__backdrop"
      loading="eager"
      fetchpriority="high"
    />

    <!-- Vertical reel-edge markers — like a film strip index -->
    <div class="cinema-hero__reel-edge" aria-hidden="true">
      <span>Reel 047 · 70mm</span>
      <span>Auditorium 01</span>
      <span>Final Cut · Vol. XXIII</span>
    </div>

    <!-- Feature eyebrow (top-left) -->
    <div class="cinema-hero__eyebrow" aria-hidden="true">
      <span class="cinema-hero__eyebrow-dot" />
      <b>Now Showing</b>
      <span class="cinema-hero__eyebrow-rule" />
      <span>Feature No. {{ featureNum }}</span>
    </div>

    <!-- Telemetry cluster (top-right) -->
    <div class="cinema-hero__telemetry" aria-hidden="true">
      <div class="cinema-hero__clock">
        <ClientOnly>
          {{ clock }}
          <template #fallback>
            <span>&mdash;&mdash;:&mdash;&mdash;:&mdash;&mdash;</span>
          </template>
        </ClientOnly>
      </div>
      <div class="cinema-hero__telemetry-row">
        <span>42°N 71°W</span>
        <span>12°C · Clear</span>
      </div>
      <div class="cinema-hero__telemetry-row">
        <span>Auditorium 1 · 217/220</span>
      </div>
      <div class="cinema-hero__telemetry-row">
        <span>Doors 19:00 · Showtime 19:30</span>
      </div>
    </div>

    <!-- Main grid: feature stack + showtime side panel -->
    <div class="cinema-hero__inner">
      <div class="cinema-hero__feature">
        <h1 class="cinema-hero__title">
          {{ movie.title }}
        </h1>
        <p v-if="movie.tagline" class="cinema-hero__sub">{{ movie.tagline }}</p>

        <div class="cinema-hero__stats" aria-label="Feature statistics">
          <div class="cinema-hero__stat">
            <span>Runtime</span>
            <b>{{ runtimeDisplay }}</b>
          </div>
          <div class="cinema-hero__stat">
            <span>Release</span>
            <b>{{ new Date(movie.releaseDate).getFullYear() }}</b>
          </div>
          <div v-if="movie.genres.length > 0" class="cinema-hero__stat">
            <span>Programme</span>
            <b>{{ movie.genres[0]?.name }}</b>
          </div>
          <div v-if="scoreDisplay !== null" class="cinema-hero__stat">
            <span>Critic</span>
            <b>
              {{ scoreDisplay }}<span class="cinema-hero__stat-suffix">/100</span>
            </b>
          </div>
        </div>

        <div class="cinema-hero__cta">
          <CvButton
            variant="primary"
            :href="ticketsHref"
            :aria-label="`Get tickets for ${movie.title}`"
          >
            <template #icon-left>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M4 4h2v16H4zm4 0l10 8-10 8z" />
              </svg>
            </template>
            Get Tickets
          </CvButton>
          <a
            v-if="trailerHref"
            :href="trailerHref"
            target="_blank"
            rel="noopener noreferrer"
            class="cinema-hero__ghost"
            :aria-label="`Watch trailer for ${movie.title} (opens in new tab)`"
          >
            Watch Trailer
            <span aria-hidden="true">&nbsp;&rarr;</span>
          </a>
        </div>
      </div>

      <!-- Showtime side panel -->
      <aside class="cinema-hero__panel" aria-label="Upcoming screening times">
        <div class="cinema-hero__panel-head">
          <span class="cinema-hero__panel-label">This Week</span>
        </div>
        <div class="cinema-hero__panel-title">Find a Showtime</div>
        <div v-if="heroSlots.length > 0" class="cinema-hero__times">
          <NuxtLink
            v-for="slot in heroSlots"
            :key="slot.id"
            :to="{ path: `/purchase/${slot.id}`, query: { loc: slot.locationSlug } }"
            class="cinema-hero__time"
            :aria-label="`Buy tickets for ${movie.title} at ${slot.time} ${slot.meridiem}`"
          >
            <span class="cinema-hero__time-value">{{ slot.time }}</span>
            <span class="cinema-hero__time-mer">{{ slot.meridiem }}</span>
          </NuxtLink>
        </div>
        <p v-else class="cinema-hero__times-empty">
          Next screenings are being scheduled — check the calendar.
        </p>
        <div class="cinema-hero__panel-foot">
          <span>Reserved seating · from $18.50</span>
          <NuxtLink to="/whats-on" class="cinema-hero__panel-link">View calendar &rarr;</NuxtLink>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.cinema-hero {
  position: relative;
  min-height: calc(100vh - 6rem);
  padding: var(--space-3xl) var(--space-md) var(--space-2xl);
  overflow: hidden;
  display: grid;
  grid-template-columns: 1fr;
  align-items: end;
  isolation: isolate;
  color: var(--on-surface);
}

@media (min-width: 40rem) {
  .cinema-hero {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .cinema-hero {
    padding-inline: var(--space-2xl);
  }
}

/* Atmosphere layers */
.cinema-hero__bg {
  position: absolute;
  inset: 0;
  z-index: var(--z-recessed-deep);
  background:
    radial-gradient(ellipse 80% 60% at 75% 30%, rgb(85 0 0 / 0.45), transparent 60%),
    radial-gradient(ellipse 60% 80% at 20% 80%, rgb(138 58 32 / 0.25), transparent 60%),
    linear-gradient(180deg, #0a0605 0%, #1a0f0b 40%, var(--surface-container-lowest) 100%);
}

.cinema-hero__bg::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    repeating-linear-gradient(90deg, transparent 0 7.375rem, rgb(218 199 105 / 0.04) 7.375rem 7.4375rem),
    repeating-linear-gradient(0deg, transparent 0 3.75rem, rgb(204 198 182 / 0.02) 3.75rem 3.8125rem);
  pointer-events: none;
}

.cinema-hero__bg::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 60%, var(--surface) 100%);
}

.cinema-hero__backdrop {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 25%;
  opacity: 0.35;
  mix-blend-mode: luminosity;
  z-index: var(--z-recessed);
}

/* Reel-edge markers — vertical text along left gutter */
.cinema-hero__reel-edge {
  display: none;
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3rem;
  flex-direction: column;
  justify-content: space-around;
  padding-block: var(--space-xl);
  pointer-events: none;
}

@media (min-width: 60rem) {
  .cinema-hero__reel-edge {
    display: flex;
  }
}

.cinema-hero__reel-edge span {
  font-size: 0.5625rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--outline-variant);
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  text-align: center;
}

.cinema-hero__reel-edge::before,
.cinema-hero__reel-edge::after {
  content: '';
  position: absolute;
  left: 0.875rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%; /* token-exception: reel sprocket */
  border: var(--border-hairline) solid var(--outline-variant);
  opacity: 0.5;
}

.cinema-hero__reel-edge::before { top: var(--space-xl); }
.cinema-hero__reel-edge::after { bottom: var(--space-xl); }

/* Eyebrow (top-left) */
.cinema-hero__eyebrow {
  position: absolute;
  top: var(--space-md);
  left: var(--space-md);
  display: flex;
  align-items: center;
  gap: var(--space-md);
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

@media (min-width: 60rem) {
  .cinema-hero__eyebrow {
    left: var(--space-2xl);
  }
}

.cinema-hero__eyebrow b {
  color: var(--secondary);
  font-weight: 500;
}

.cinema-hero__eyebrow-dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%; /* token-exception: decorative dot */
  background-color: var(--secondary);
}

.cinema-hero__eyebrow-rule {
  width: 4rem;
  height: var(--border-hairline);
  background-color: var(--outline-variant);
  opacity: 0.6;
}

/* Telemetry (top-right) */
.cinema-hero__telemetry {
  display: none;
  position: absolute;
  top: var(--space-md);
  right: var(--space-2xl);
  flex-direction: column;
  align-items: flex-end;
  gap: 0.3rem;
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
}

@media (min-width: 60rem) {
  .cinema-hero__telemetry {
    display: flex;
  }
}

.cinema-hero__clock {
  font-family: var(--font-display);
  font-size: 1.375rem;
  letter-spacing: 0.04em;
  color: var(--on-surface);
  font-variant-numeric: tabular-nums;
}

.cinema-hero__telemetry-row {
  display: flex;
  gap: var(--space-md);
}

/* Inner composition */
.cinema-hero__inner {
  position: relative;
  z-index: 1;
  max-width: 90rem;
  margin: 0 auto;
  width: 100%;
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-2xl);
  align-items: end;
  padding-top: var(--space-2xl);
}

@media (min-width: 60rem) {
  .cinema-hero__inner {
    grid-template-columns: 1fr auto;
  }
}

.cinema-hero__feature {
  animation: cinema-hero-reveal var(--duration-cinematic) var(--ease-enter) both;
}

.cinema-hero__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(3rem, 7vw, 7.5rem);
  line-height: 0.92;
  letter-spacing: -0.035em;
  color: var(--on-surface);
  text-wrap: balance;
  max-width: 16ch;
  margin: 0 0 var(--space-md);
}

.cinema-hero__sub {
  font-family: var(--font-body);
  font-size: 1.125rem;
  color: var(--tertiary);
  max-width: 42ch;
  line-height: 1.5;
  text-wrap: pretty;
  margin: 0 0 var(--space-xl);
}

.cinema-hero__stats {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-md) var(--space-xl);
  margin-bottom: var(--space-xl);
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.cinema-hero__stat {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
}

.cinema-hero__stat b {
  font-family: var(--font-display);
  font-size: 1rem;
  color: var(--on-surface);
  font-weight: 500;
  letter-spacing: -0.01em;
  text-transform: none;
}

.cinema-hero__stat-suffix {
  color: var(--on-tertiary-fixed-variant);
  font-size: 0.75rem;
}

.cinema-hero__cta {
  display: flex;
  gap: var(--space-md);
  align-items: center;
  flex-wrap: wrap;
  animation: cinema-hero-reveal var(--duration-cinematic) var(--ease-enter) 150ms both;
}

.cinema-hero__ghost {
  display: inline-flex;
  align-items: center;
  height: 3rem;
  padding-inline: var(--space-sm);
  font-family: var(--font-body);
  font-size: 1rem;
  color: var(--on-surface);
  text-decoration: none;
  letter-spacing: 0.01em;
  position: relative;
  transition: color var(--duration-standard) var(--ease-standard);
}

.cinema-hero__ghost::after {
  content: '';
  position: absolute;
  left: var(--space-sm);
  right: var(--space-sm);
  bottom: 0.875rem;
  height: var(--border-hairline);
  background-color: var(--on-surface);
  transform-origin: left;
  transition:
    transform var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard);
}

.cinema-hero__ghost:hover {
  color: var(--secondary);
}

.cinema-hero__ghost:hover::after {
  background-color: var(--secondary);
  transform: scaleX(1.05);
}

/* Side panel — glass */
.cinema-hero__panel {
  width: 100%;
  padding: var(--space-lg);
  background-color: rgb(20 20 20 / 0.85);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.25);
  border-radius: var(--radius-sm);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  animation: cinema-hero-reveal var(--duration-cinematic) var(--ease-enter) 200ms both;
}

@supports ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
  .cinema-hero__panel {
    background-color: rgb(20 20 20 / 0.55);
    -webkit-backdrop-filter: blur(1.5rem);
    backdrop-filter: blur(1.5rem);
  }
}

@media (min-width: 60rem) {
  .cinema-hero__panel {
    width: 23.75rem;
  }
}

.cinema-hero__panel-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.cinema-hero__panel-label {
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.cinema-hero__panel-title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  letter-spacing: -0.015em;
  line-height: 1.05;
  color: var(--on-surface);
}

.cinema-hero__times {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.375rem;
  margin-top: 0.25rem;
}

.cinema-hero__time {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.625rem 0.25rem;
  background-color: rgb(42 42 42 / 0.4);
  border-radius: var(--radius-sm);
  color: var(--on-surface);
  text-decoration: none;
  transition:
    background-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard),
    transform var(--duration-micro) var(--ease-standard);
}

.cinema-hero__time:hover {
  background-color: var(--primary-container);
  color: var(--primary);
  transform: translateY(-0.0625rem);
}

.cinema-hero__times-empty {
  margin-top: 0.25rem;
  font-size: 0.8125rem;
  line-height: 1.5;
  color: var(--tertiary);
}

.cinema-hero__time-value {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  letter-spacing: -0.01em;
}

.cinema-hero__time-mer {
  font-size: 0.5625rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  opacity: 0.7;
  margin-top: 0.125rem;
}

.cinema-hero__panel-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  padding-top: var(--space-sm);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.cinema-hero__panel-link {
  color: var(--secondary);
  text-decoration: none;
}

.cinema-hero__panel-link:hover {
  text-decoration: underline;
}

/* Motion */
@keyframes cinema-hero-reveal {
  from {
    opacity: 0;
    transform: translateY(1rem);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .cinema-hero__feature,
  .cinema-hero__cta,
  .cinema-hero__panel {
    animation: none;
  }
}
</style>
