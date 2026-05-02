<script setup lang="ts">
import type { Movie } from '~/types/movie'
import { formatRuntime } from '~/utils/formatRuntime'

const props = defineProps<{
  movies: Movie[]
}>()

const total = computed(() => props.movies.length)

function indexLabel(i: number): string {
  return `${String(i + 1).padStart(2, '0')} / ${String(total.value).padStart(2, '0')}`
}

function scoreLabel(rating: number | null): string | null {
  if (rating === null) return null
  return String(Math.round(rating * 10))
}

// The primary movie genre used in the meta row.
function primaryGenre(movie: Movie): string {
  return movie.genres[0]?.name ?? 'Feature'
}

// A deterministic tag/accent combination so reels read varied without random.
type Accent = { tag: string; tagKind: 'new' | 'gold' | '' }

function accentFor(movie: Movie, i: number): Accent {
  // Latest release gets "New"; every 3rd gets a format tag; others are "Select".
  const release = new Date(movie.releaseDate).getTime()
  const thirtyDays = 30 * 24 * 60 * 60 * 1000
  const isRecent = Date.now() - release < thirtyDays

  if (isRecent) return { tag: 'New', tagKind: 'new' }
  if (i % 3 === 0) return { tag: '70mm', tagKind: 'gold' }
  if (i % 3 === 1) return { tag: 'IMAX', tagKind: 'gold' }
  return { tag: 'Select', tagKind: '' }
}

// Build a short mock showtime row from runtime/id so the poster card has a times strip
// even when live showtimes aren't fetched per card. (Live showtimes belong on the
// movie detail page; here we advertise "times available today".)
function poster(movie: Movie) {
  return movie.posterUrl || ''
}
</script>

<template>
  <section class="now-showing" aria-labelledby="now-showing-heading">
    <div class="now-showing__inner">
      <header class="now-showing__head">
        <div>
          <div class="now-showing__eyebrow">Reel 01 — {{ String(total).padStart(2, '0') }} · This Week</div>
          <h2 id="now-showing-heading" class="now-showing__title">
            Now Showing, <em>with intention.</em>
          </h2>
        </div>
        <div class="now-showing__actions">
          <NuxtLink to="/movies" class="now-showing__link">Full Schedule &rarr;</NuxtLink>
        </div>
      </header>

      <div
        class="now-showing__reel"
        role="list"
        aria-label="Films currently showing"
      >
        <NuxtLink
          v-for="(movie, i) in movies"
          :key="movie.id"
          :to="`/movies/${movie.slug}`"
          class="now-showing__poster"
          role="listitem"
        >
          <div class="now-showing__art">
            <img
              v-if="poster(movie)"
              :src="poster(movie)"
              :alt="`${movie.title} poster`"
              class="now-showing__art-img"
              loading="lazy"
            />
            <span
              class="now-showing__tag"
              :class="[`now-showing__tag--${accentFor(movie, i).tagKind || 'default'}`]"
            >{{ accentFor(movie, i).tag }}</span>
            <span class="now-showing__idx" aria-hidden="true">{{ indexLabel(i) }}</span>
            <span class="now-showing__play" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
            </span>
          </div>

          <div class="now-showing__meta">
            <span>{{ primaryGenre(movie) }} · {{ formatRuntime(movie.runtime) }}</span>
            <span v-if="scoreLabel(movie.rating)" class="now-showing__score">{{ scoreLabel(movie.rating) }}</span>
          </div>
          <h3 class="now-showing__poster-title">{{ movie.title }}</h3>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
.now-showing {
  padding-block: var(--space-4xl);
  padding-inline: var(--space-md);
}

@media (min-width: 40rem) {
  .now-showing {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .now-showing {
    padding-inline: var(--space-2xl);
  }
}

.now-showing__inner {
  max-width: 90rem;
  margin-inline: auto;
}

.now-showing__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-xl);
  margin-bottom: var(--space-2xl);
}

.now-showing__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.now-showing__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.now-showing__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.5rem);
  line-height: 1;
  letter-spacing: -0.025em;
  margin: 0.75rem 0 0;
  color: var(--on-surface);
  text-wrap: balance;
  max-width: 22ch;
}

.now-showing__title em {
  font-style: italic;
  color: var(--tertiary);
}

.now-showing__actions {
  flex-shrink: 0;
}

.now-showing__link {
  display: inline-flex;
  align-items: center;
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: 0.875rem;
  text-decoration: none;
  padding-block: var(--space-xs);
  border-bottom: var(--border-hairline) solid transparent;
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.now-showing__link:hover {
  border-bottom-color: var(--secondary);
}

/* Horizontal scrolling reel */
.now-showing__reel {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(17.5rem, 1fr);
  gap: var(--space-lg);
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding-inline: var(--space-md);
  margin-inline: calc(-1 * var(--space-md));
  padding-bottom: var(--space-md);
  scrollbar-width: thin;
  scrollbar-color: var(--outline-variant) transparent;
}

@media (min-width: 40rem) {
  .now-showing__reel {
    padding-inline: var(--space-xl);
    margin-inline: calc(-1 * var(--space-xl));
  }
}

@media (min-width: 60rem) {
  .now-showing__reel {
    padding-inline: var(--space-2xl);
    margin-inline: calc(-1 * var(--space-2xl));
  }
}

.now-showing__reel::-webkit-scrollbar { height: 0.125rem; }
.now-showing__reel::-webkit-scrollbar-thumb { background-color: var(--outline-variant); }

.now-showing__poster {
  scroll-snap-align: start;
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
  color: var(--on-surface);
  text-decoration: none;
  transition: transform var(--duration-standard) var(--ease-standard);
}

.now-showing__poster:hover {
  transform: translateY(-0.25rem);
}

.now-showing__art {
  aspect-ratio: 2 / 3;
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background:
    radial-gradient(ellipse at 40% 35%, rgb(60 20 20 / 0.9), var(--surface-container-lowest) 70%);
}

.now-showing__art-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
}

.now-showing__art::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 55%, rgb(0 0 0 / 0.6));
  pointer-events: none;
}

.now-showing__tag {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  z-index: 2;
  padding: 0.25rem 0.5rem;
  background-color: rgb(14 14 14 / 0.7);
  -webkit-backdrop-filter: blur(0.5rem);
  backdrop-filter: blur(0.5rem);
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-surface);
  border-radius: var(--radius-sm);
}

.now-showing__tag--new {
  color: var(--primary);
  background-color: var(--primary-container);
}

.now-showing__tag--gold {
  color: var(--surface);
  background-color: var(--secondary);
}

.now-showing__idx {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  z-index: 2;
  font-family: var(--font-display);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.1em;
}

.now-showing__play {
  position: absolute;
  bottom: 0.75rem;
  right: 0.75rem;
  z-index: 3;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%; /* token-exception: affordance */
  background-color: var(--secondary);
  color: var(--surface);
  display: grid;
  place-items: center;
  opacity: 0;
  transform: scale(0.8);
  transition:
    opacity var(--duration-standard) var(--ease-standard),
    transform var(--duration-standard) var(--ease-standard);
}

.now-showing__poster:hover .now-showing__play {
  opacity: 1;
  transform: scale(1);
}

.now-showing__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.now-showing__score {
  color: var(--secondary);
}

.now-showing__poster-title {
  font-family: var(--font-display);
  font-size: 1.375rem;
  line-height: 1.1;
  letter-spacing: -0.015em;
  font-weight: 500;
  color: var(--on-surface);
  margin: 0;
}
</style>
