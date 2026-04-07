<script setup lang="ts">
import type { Showtime } from '~/types/showtime'

// Get slug from route params
const route = useRoute()
const slug = route.params.slug as string

// Fetch movie detail (SSR/ISR compatible)
const { data: movieData, error: movieError } = useMovies().getMovie(slug)
const movie = computed(() => movieData.value?.data ?? null)

// Handle 404
if (movieError.value) {
  throw createError({ statusCode: 404, statusMessage: 'Movie not found' })
}

// Client-only showtime fetch (location-dependent)
const { activeLocation } = useLocations()
const showtimes = ref<Showtime[]>([])
const showtimesLoading = ref(false)

async function fetchShowtimes() {
  const locSlug = activeLocation.value?.slug
  if (!locSlug) {
    showtimes.value = []
    return
  }
  showtimesLoading.value = true
  try {
    const res = await apiFetch<{ data: Showtime[] }>(
      `/api/locations/${locSlug}/movies/${slug}/showtimes`,
    )
    showtimes.value = res.data
  } catch {
    showtimes.value = []
  } finally {
    showtimesLoading.value = false
  }
}

if (import.meta.client) {
  watch(activeLocation, () => fetchShowtimes(), { immediate: true })
}

// SEO
useHead({
  title: computed(() =>
    movie.value
      ? `${movie.value.title} — Showtimes & Tickets — Final Cut`
      : 'Movie — Final Cut',
  ),
  meta: [
    {
      name: 'description',
      content: computed(() => {
        if (!movie.value) return 'Get showtimes and tickets at Final Cut.'
        const text = movie.value.tagline || movie.value.synopsis
        const truncated = text.length > 150 ? text.slice(0, 150).trimEnd() + '…' : text
        return `${truncated} Get showtimes and tickets at Final Cut.`
      }),
    },
  ],
})
</script>

<template>
  <div v-if="movie" class="movie-page">
    <!-- 1. Wide Frame Hero (atmospheric, no CTAs) -->
    <MovieHero :movie="movie" />

    <!-- 2. Establishing Shot 65/35 -->
    <div class="movie-page__content">
      <div class="establishing-shot">
        <!-- Left 65%: Movie detail (synopsis, genres, runtime, rating, trailer, cast) -->
        <div class="establishing-shot__primary">
          <MovieDetail :movie="movie" />
        </div>

        <!-- Right 35%: Showtimes + Rating -->
        <aside class="establishing-shot__secondary">
          <div class="movie-page__sidebar">
            <MovieRatingBadge :rating="movie.rating" />

            <ClientOnly>
              <ShowtimeSelector
                :showtimes="showtimes"
                :movie-slug="movie.slug"
              />
              <template #fallback>
                <CvSkeletonLoader variant="text" :lines="4" />
              </template>
            </ClientOnly>
          </div>
        </aside>
      </div>
    </div>
  </div>
</template>

<style scoped>
.movie-page__content {
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-2xl);
  padding-block: var(--space-3xl);
}

.movie-page__sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  position: sticky;
  top: 7rem; /* header (4rem) + ticker (2rem) + breathing room */
}

@media (max-width: 59.999rem) {
  .movie-page__content {
    padding-inline: var(--space-md);
  }
}
</style>
