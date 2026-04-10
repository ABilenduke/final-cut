<script setup lang="ts">
import type { Showtime } from '~/types/showtime'

// Get slug reactively so param-only navigation updates the page
const route = useRoute()
const slug = computed(() => route.params.slug as string)

// Fetch movie detail (SSR/ISR compatible)
// useApiFetch with computed URL re-fetches when slug changes
const { data: movieData, error: movieError } = useApiFetch<{ data: import('~/types/movie').Movie }>(
  computed(() => `/api/movies/${slug.value}`),
)
const movie = computed(() => movieData.value?.data ?? null)

// Handle fetch errors — watch so async client-side navigation failures are caught
watch(
  movieError,
  (error) => {
    if (!error) return

    // Extract status from the fetch error (ofetch / Nuxt error shape)
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
let fetchGeneration = 0 // Guards against stale async responses

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

// SEO
// siteUrl must be set via NUXT_PUBLIC_SITE_URL; never derive from request on ISR pages
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
    <!-- 1. Wide Frame Hero (atmospheric, no CTAs) -->
    <MovieHero :movie="movie" />

    <!-- 2. Establishing Shot 65/35 -->
    <div class="container movie-page__content">
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
              <CvSkeletonLoader v-if="showtimesLoading" variant="text" :lines="4" />
              <ShowtimeSelector
                v-else
                :showtimes="showtimes"
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
  padding-block: var(--space-3xl);
}

.movie-page__sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  position: sticky;
  top: 7rem; /* header (4rem) + ticker (2rem) + breathing room */
}

</style>
