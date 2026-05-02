<script setup lang="ts">
import type { Movie } from '~/types/movie'

const props = defineProps<{
  excludeSlug: string
}>()

// Movies themselves are shared across locations (see docs/architecture/
// DATA_MODELS.md — only showtimes are location-scoped). The "Also
// Showing" section is therefore a catalog-wide marquee, not a per-
// location listing. If a future product decision ties movie availability
// to a specific venue, pass `activeLocation.slug` into the API call and
// filter here before rendering.
// TODO(backend): add `GET /api/locations/:slug/movies` if per-location
// marquees become a requirement.
const { nowShowing } = useMovies()
const { data } = nowShowing({ per_page: 6 })

const related = computed<Movie[]>(() => {
  const list = data.value?.data ?? []
  return list.filter(m => m.slug !== props.excludeSlug).slice(0, 4)
})

// Per-movie atmospheric gradient. Keeps the deterministic hue as a low
// editorial tint, but resolves through brand vignette bloom — primary-container
// to surface-container-lowest — so the section stays in the Final Cut frame.
// See DS § Glass & Gradient.
function posterGradient(id: number): string {
  const hue = (id * 41) % 360
  const tint = `hsl(${hue}deg 35% 35%)`
  return `radial-gradient(ellipse at 60% 35%, color-mix(in oklch, ${tint} 30%, var(--primary-container)), var(--primary-container) 35%, var(--surface-container-lowest) 100%)`
}

function glyphFor(title: string): string {
  return title.trim().charAt(0).toUpperCase() || '◉'
}

const genreLabelFor = (movie: Movie): string => {
  if (movie.genres.length === 0) return 'Feature'
  return movie.genres[0].name
}
</script>

<template>
  <div v-if="related.length > 0">
    <div class="bay-eyebrow">Also Showing</div>
    <h2 class="bay-title">If you're staying <em>for the week.</em></h2>

    <div class="movie-related__grid">
      <NuxtLink
        v-for="m in related"
        :key="m.slug"
        :to="`/movies/${m.slug}`"
        class="movie-related__card"
      >
        <div
          class="movie-related__art"
          :style="!m.posterUrl ? { background: posterGradient(m.id) } : undefined"
        >
          <img
            v-if="m.posterUrl"
            :src="m.posterUrl"
            :alt="`${m.title} poster`"
            class="movie-related__img"
            loading="lazy"
          />
          <span v-else class="movie-related__glyph" aria-hidden="true">
            {{ glyphFor(m.title) }}
          </span>
        </div>
        <div class="movie-related__meta">
          <span>{{ genreLabelFor(m) }} · {{ formatRuntime(m.runtime) }}</span>
          <span v-if="m.rating != null" class="movie-related__score">
            {{ Math.round(m.rating * 10) }}
          </span>
        </div>
        <div class="movie-related__title">{{ m.title }}</div>
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.movie-related__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-lg);
  margin-top: var(--space-xl);
}

.movie-related__card {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  transition: transform var(--duration-standard) var(--ease-standard);
  text-decoration: none;
  color: inherit;
}

.movie-related__card:hover {
  transform: translateY(-0.1875rem);
}

.movie-related__card:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.movie-related__art {
  aspect-ratio: 2 / 3;
  border-radius: var(--radius-sm);
  position: relative;
  overflow: hidden;
}

.movie-related__art::after {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.07) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
  pointer-events: none;
}

.movie-related__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
}

.movie-related__glyph {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 5rem;
  color: rgba(255, 255, 255, 0.1);
}

.movie-related__meta {
  display: flex;
  justify-content: space-between;
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-related__score {
  color: var(--secondary);
}

.movie-related__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  letter-spacing: -0.01em;
  font-weight: 500;
  line-height: 1.2;
  color: var(--on-surface);
}

@media (max-width: 68.75rem) {
  .movie-related__grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (prefers-reduced-motion: reduce) {
  .movie-related__card {
    transition: none;
  }
}
</style>
