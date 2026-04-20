<script setup lang="ts">
import type { Showtime } from '~/types/showtime'

// Get slug reactively so param-only navigation updates the page
const route = useRoute()
const slug = computed(() => route.params.slug as string)

// Fetch movie detail (SSR/ISR compatible)
const { data: movieData, error: movieError } = useApiFetch<{ data: import('~/types/movie').Movie }>(
  computed(() => `/api/movies/${slug.value}`),
)
const movie = computed(() => movieData.value?.data ?? null)

// Handle fetch errors — watch so async client-side navigation failures are caught
watch(
  movieError,
  (error) => {
    if (!error) return

    const fetchError = error as { statusCode?: number; response?: { status?: number } }
    const status = fetchError.statusCode ?? fetchError.response?.status

    if (status === 404) {
      throw createError({ statusCode: 404, statusMessage: 'Movie not found' })
    }

    throw createError({
      statusCode: status ?? 500,
      statusMessage: 'Failed to load movie',
    })
  },
  { immediate: true },
)

// Client-only showtime fetch (location + slug dependent)
const { activeLocation } = useLocations()
const showtimes = ref<Showtime[]>([])
const showtimesLoading = ref(false)
let fetchGeneration = 0

async function fetchShowtimes() {
  const locSlug = activeLocation.value?.slug
  const movieSlug = slug.value
  if (!locSlug || !movieSlug) {
    showtimes.value = []
    return
  }
  const generation = ++fetchGeneration
  showtimesLoading.value = true
  try {
    const res = await apiFetch<{ data: Showtime[] }>(
      `/api/locations/${locSlug}/movies/${movieSlug}/showtimes`,
    )
    if (generation === fetchGeneration) {
      showtimes.value = res.data
    }
  } catch {
    if (generation === fetchGeneration) {
      showtimes.value = []
    }
  } finally {
    if (generation === fetchGeneration) {
      showtimesLoading.value = false
    }
  }
}

if (import.meta.client) {
  watch([activeLocation, slug], () => fetchShowtimes(), { immediate: true })
}

// SEO — siteUrl must be set via NUXT_PUBLIC_SITE_URL; never derive from request on ISR pages
const siteUrl = useRuntimeConfig().public.siteUrl as string

const seoDescription = computed(() => {
  if (!movie.value) return 'Get showtimes and tickets at Final Cut.'
  const text = movie.value.tagline || movie.value.synopsis
  const truncated = text.length > 150 ? text.slice(0, 150).trimEnd() + '…' : text
  return `${truncated} Get showtimes and tickets at Final Cut.`
})

useHead({
  title: computed(() =>
    movie.value
      ? `${movie.value.title} — Showtimes & Tickets — Final Cut`
      : 'Movie — Final Cut',
  ),
  meta: [
    { name: 'description', content: seoDescription },
    { property: 'og:title', content: computed(() => movie.value ? `${movie.value.title} — Showtimes & Tickets` : 'Movie — Final Cut') },
    { property: 'og:description', content: seoDescription },
    ...(siteUrl ? [{ property: 'og:url', content: computed(() => `${siteUrl}/movies/${slug.value}`) }] : []),
    { property: 'og:type', content: 'video.movie' },
    { property: 'og:image', content: computed(() => movie.value?.posterUrl ?? '') },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: computed(() => {
        if (!movie.value) return '{}'
        return safeJsonLd({
          '@context': 'https://schema.org',
          '@type': 'Movie',
          name: movie.value.title,
          description: movie.value.synopsis,
          image: movie.value.posterUrl,
          datePublished: movie.value.releaseDate,
          genre: movie.value.genres?.map(g => g.name) ?? [],
          duration: movie.value.runtime ? `PT${movie.value.runtime}M` : undefined,
          aggregateRating: movie.value.rating ? {
            '@type': 'AggregateRating',
            ratingValue: movie.value.rating,
            bestRating: 10,
          } : undefined,
          actor: movie.value.cast?.slice(0, 5).map(c => ({
            '@type': 'Person',
            name: c.name,
          })) ?? [],
        })
      }),
    },
  ],
})
</script>

<template>
  <div v-if="movie" class="movie-page">
    <!-- Breadcrumb strip -->
    <MovieBreadcrumb :title="movie.title" />

    <!-- Atmospheric hero with poster, telemetry, crew stats, CTAs -->
    <MovieHero :movie="movie" />

    <!-- Synopsis & credits -->
    <section class="bay">
      <div class="bay-inner">
        <MovieDetail :movie="movie" />
      </div>
    </section>

    <!-- Trailer stage + clips sidebar -->
    <section
      v-if="movie.trailerKey"
      id="trailer"
      class="bay movie-page__trailer-bay"
    >
      <div class="bay-inner">
        <MovieTrailerEmbed :trailer-key="movie.trailerKey" :title="movie.title" />
      </div>
    </section>

    <!-- Cast strip -->
    <section v-if="movie.cast.length > 0" class="bay">
      <div class="bay-inner">
        <MovieCastList :cast="movie.cast" />
      </div>
    </section>

    <!-- Showtimes + seat preview -->
    <section id="showtimes" class="bay movie-page__showtimes-bay">
      <div class="bay-inner">
        <ClientOnly>
          <div v-if="showtimesLoading" class="movie-page__showtimes-loading">
            <CvSkeletonLoader variant="text" :lines="5" />
          </div>
          <template v-else>
            <ShowtimeSelector :showtimes="showtimes" />
            <MovieSeatPreview :movie="movie" />
          </template>
          <template #fallback>
            <div class="movie-page__showtimes-loading">
              <CvSkeletonLoader variant="text" :lines="5" />
            </div>
          </template>
        </ClientOnly>
      </div>
    </section>

    <!-- Press quotes + scores -->
    <section class="bay">
      <div class="bay-inner">
        <MoviePress />
      </div>
    </section>

    <!-- Related movies -->
    <section class="bay movie-page__related-bay">
      <div class="bay-inner">
        <MovieRelated :exclude-slug="movie.slug" />
      </div>
    </section>
  </div>
</template>

<style scoped>
/* The trailer and showtimes bays use the recessed surface to create
   visual punctuation between bays (matches the design's dark-on-dark rhythm). */
.movie-page__trailer-bay,
.movie-page__showtimes-bay {
  background: var(--surface-container-lowest);
}

.movie-page__related-bay {
  padding-top: var(--space-xl);
}

.movie-page__showtimes-loading {
  padding-block: var(--space-xl);
}
</style>
