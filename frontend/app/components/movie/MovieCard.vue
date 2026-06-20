<script setup lang="ts">
import type { Movie } from '~/types/movie'

const props = withDefaults(defineProps<{
  movie: Movie
  /** When true, the card targets the now-showing flow (rating chip + showtimes CTA).
   *  When false, it targets coming-soon (release date + Notify Me). */
  showShowtimes?: boolean
  /** Index marker rendered top-left over the poster (e.g. 1 → "01"). */
  index?: number
}>(), {
  showShowtimes: true,
  index: undefined,
})

const emit = defineEmits<{
  notify: [slug: string]
}>()

/** Deterministic poster gradient for films without artwork. Hashes the slug to
 *  one of a small palette so each film gets a stable, distinct backdrop. */
const POSTER_GRADIENTS: string[] = [
  'radial-gradient(ellipse at 30% 30%, #c47040 0%, #6b2818 55%, #1f0d08 100%)',
  'radial-gradient(ellipse at 35% 30%, #6a85a8 0%, #2a3858 55%, #0e1820 100%)',
  'radial-gradient(ellipse at 30% 30%, #6a4a20 0%, #2a1808 55%, #0a0402 100%)',
  'radial-gradient(ellipse at 35% 30%, #8a2020 0%, #3a0a0a 55%, #180404 100%)',
  'radial-gradient(ellipse at 30% 30%, #4a4a6a 0%, #1f1f3a 55%, #0a0a18 100%)',
  'radial-gradient(ellipse at 35% 35%, #8a9850 0%, #384828 55%, #101808 100%)',
]

function hashSlug(slug: string): number {
  let hash = 0
  for (let i = 0; i < slug.length; i++) hash = (hash * 31 + slug.charCodeAt(i)) | 0
  return Math.abs(hash)
}

const fallbackGradient = computed<string>(
  () => POSTER_GRADIENTS[hashSlug(props.movie.slug) % POSTER_GRADIENTS.length]!,
)

const glyph = computed<string>(() =>
  props.movie.title.trim().charAt(0).toUpperCase() || '·',
)

const indexLabel = computed<string | null>(() =>
  typeof props.index === 'number' ? String(props.index).padStart(2, '0') : null,
)

const ratingLabel = computed<string>(() =>
  props.movie.rating !== null ? props.movie.rating.toFixed(1) : '—',
)

const runtimeLabel = computed<string>(() => {
  // Guard against a missing/null/0 runtime (e.g. a not-yet-enriched movie, or
  // a list payload that omits it) — otherwise this formats to "NaNh NaNm".
  const raw = props.movie.runtime
  if (typeof raw !== 'number' || !Number.isFinite(raw) || raw <= 0) return ''
  const total = Math.round(raw)
  const h = Math.floor(total / 60)
  const m = total % 60
  if (h === 0) return `${m}m`
  if (m === 0) return `${h}h`
  return `${h}h ${String(m).padStart(2, '0')}m`
})

const detailHref = computed<string>(() => `/movies/${props.movie.slug}`)
</script>

<template>
  <article class="movie-card" :class="{ 'movie-card--coming': !showShowtimes }">
    <NuxtLink :to="detailHref" class="movie-card__art-link" :aria-label="`Open ${movie.title}`">
      <div
        class="movie-card__art"
        :style="{ background: fallbackGradient }"
      >
        <img
          v-if="movie.posterUrl"
          :src="movie.posterUrl"
          :alt="`${movie.title} poster`"
          class="movie-card__poster"
          loading="lazy"
        />
        <span v-else class="movie-card__glyph" aria-hidden="true">{{ glyph }}</span>

        <span v-if="indexLabel" class="movie-card__idx">Reel · {{ indexLabel }}</span>

        <span v-if="!showShowtimes" class="movie-card__tag movie-card__tag--soon">Coming soon</span>
        <span v-else class="movie-card__tag">Now playing</span>

        <span v-if="movie.rating !== null && showShowtimes" class="movie-card__rating">
          <span aria-hidden="true">★</span>
          <span>{{ ratingLabel }}</span>
        </span>
      </div>
    </NuxtLink>

    <div class="movie-card__body">
      <h3 class="movie-card__title">
        <NuxtLink :to="detailHref" class="movie-card__title-link">{{ movie.title }}</NuxtLink>
      </h3>

      <p v-if="movie.tagline" class="movie-card__tagline">{{ movie.tagline }}</p>

      <div class="movie-card__meta">
        <span v-if="runtimeLabel" class="movie-card__diet movie-card__diet--time">{{ runtimeLabel }}</span>
        <span
          v-for="genre in movie.genres.slice(0, 3)"
          :key="genre.id"
          class="movie-card__diet"
        >
          {{ genre.name }}
        </span>
      </div>
    </div>

    <div class="movie-card__foot">
      <span v-if="!showShowtimes" class="movie-card__release">
        Releases <b>{{ formatDate(movie.releaseDate) }}</b>
      </span>
      <span v-else class="movie-card__release movie-card__release--muted">
        Showtimes nightly
      </span>

      <NuxtLink
        v-if="showShowtimes"
        :to="detailHref"
        class="movie-card__cta"
      >
        <span class="movie-card__cta-arrow" aria-hidden="true">→</span>
        Showtimes
      </NuxtLink>
      <button
        v-else
        type="button"
        class="movie-card__cta"
        :aria-label="`Notify me when ${movie.title} releases`"
        @click="emit('notify', movie.slug)"
      >
        <span class="movie-card__cta-arrow" aria-hidden="true">+</span>
        Notify me
      </button>
    </div>
  </article>
</template>

<style scoped>
.movie-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
  border-radius: var(--radius-card);
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.movie-card:hover {
  border-color: rgb(var(--secondary-rgb) / 0.35);
}

/* Poster art */
.movie-card__art-link {
  display: block;
  text-decoration: none;
  color: inherit;
}

.movie-card__art {
  position: relative;
  aspect-ratio: 2 / 3;
  border-radius: var(--radius-sm);
  overflow: hidden;
  display: grid;
  place-items: center;
}

.movie-card__art::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.07) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
  pointer-events: none;
  z-index: 1;
}

.movie-card__art::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: linear-gradient(180deg, transparent 55%, rgba(0, 0, 0, 0.55) 100%);
  pointer-events: none;
  z-index: 1;
}

.movie-card__poster {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
  z-index: 0;
}

.movie-card__glyph {
  position: relative;
  z-index: 0;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 5.5rem;
  line-height: 0.8;
  color: rgba(255, 255, 255, 0.16);
  letter-spacing: -0.05em;
}

.movie-card__idx {
  position: absolute;
  top: 0.55rem;
  right: 0.55rem;
  z-index: 2;
  font-family: var(--font-display);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  font-variant-numeric: tabular-nums;
}

.movie-card__tag {
  position: absolute;
  top: 0.55rem;
  left: 0.55rem;
  z-index: 2;
  font-family: var(--font-body);
  font-size: 0.5625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.7);
  background-color: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  padding: 0.25rem 0.45rem;
  border-radius: var(--radius-sm);
}

.movie-card__tag--soon {
  color: var(--secondary);
  background-color: rgba(14, 14, 14, 0.75);
  border: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.3);
}

.movie-card__rating {
  position: absolute;
  bottom: 0.55rem;
  right: 0.55rem;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-family: var(--font-display);
  font-size: 0.8125rem;
  font-weight: 500;
  letter-spacing: 0.02em;
  color: var(--secondary);
  background-color: rgba(14, 14, 14, 0.75);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  padding: 0.2rem 0.45rem;
  border-radius: var(--radius-sm);
  border: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.25);
  font-variant-numeric: tabular-nums;
}

/* Body */
.movie-card__body {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding-top: 0.4rem;
}

.movie-card__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.15;
  margin: 0;
  text-wrap: pretty;
}

.movie-card__title-link {
  color: var(--on-surface);
  text-decoration: none;
  transition: color var(--duration-standard) var(--ease-standard);
}

.movie-card__title-link:hover,
.movie-card__title-link:focus-visible {
  color: var(--secondary);
}

.movie-card__tagline {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  font-style: italic;
  line-height: 1.45;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.movie-card__meta {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-top: 0.1rem;
}

.movie-card__diet {
  font-family: var(--font-body);
  font-size: 0.5625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding: 0.2rem 0.4rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
  border-radius: var(--radius-sm);
}

.movie-card__diet--time {
  color: var(--tertiary);
  border-color: rgb(var(--outline-variant-rgb) / 0.5);
  font-variant-numeric: tabular-nums;
}

/* Footer */
.movie-card__foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-sm);
  padding-top: 0.6rem;
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.25);
  margin-top: auto;
}

.movie-card__release {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-card__release b {
  color: var(--tertiary);
  font-family: var(--font-display);
  font-weight: 500;
  letter-spacing: 0.04em;
  text-transform: none;
}

.movie-card__release--muted {
  color: var(--on-tertiary-fixed-variant);
}

.movie-card__cta {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.5);
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--on-surface);
  background-color: var(--surface-container);
  cursor: pointer;
  text-decoration: none;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.movie-card__cta:hover,
.movie-card__cta:focus-visible {
  border-color: var(--secondary);
  color: var(--secondary);
}

.movie-card__cta-arrow {
  color: var(--secondary);
  font-family: var(--font-display);
  font-size: 0.9rem;
  line-height: 1;
}
</style>
