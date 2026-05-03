<script setup lang="ts">
import type { Movie } from '~/types/movie'
import type { FeaturedSlide } from '~/types/featured-slide'

// --- SSR/ISR data ---
const { nowShowing } = useMovies()
const { data: slidesData } = useFeaturedSlides()

const { data: nowShowingData } = nowShowing({ per_page: 12 })
const nowShowingMovies = computed<Movie[]>(() => nowShowingData.value?.data ?? [])

// Pass empty array when the API is pending/errored — the carousel's own
// empty-state fallback renders the brand slide.
const featuredSlides = computed<FeaturedSlide[]>(() => slidesData.value?.data ?? [])

// Select featured movie from listing data (pure function, ISR-safe)
const featuredMovie = computed<Movie | null>(
  () => selectFeaturedMovie(nowShowingMovies.value) ?? null,
)

// --- SEO ---
const siteUrl = useRuntimeConfig().public.siteUrl as string

useHead({
  title: 'Final Cut \u2014 Now Showing & Tickets',
  meta: [
    {
      name: 'description',
      content: 'Now showing at Final Cut. Get tickets, browse showtimes, and discover upcoming events.',
    },
    { property: 'og:title', content: 'Final Cut \u2014 Now Showing & Tickets' },
    { property: 'og:description', content: 'Now showing at Final Cut. Get tickets, browse showtimes, and discover upcoming events.' },
    ...(siteUrl ? [{ property: 'og:url', content: siteUrl }] : []),
    { property: 'og:type', content: 'website' },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: computed(() => safeJsonLd({
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: 'Now Showing at Final Cut',
        itemListElement: nowShowingMovies.value.slice(0, 8).map((movie, i) => ({
          '@type': 'ListItem',
          position: i + 1,
          item: {
            '@type': 'Movie',
            name: movie.title,
            ...(siteUrl ? { url: `${siteUrl}/movies/${movie.slug}` } : {}),
          },
        })),
      })),
    },
  ],
})
</script>

<template>
  <div class="home-page">
    <!-- 1. Featured hero carousel — admin-curated slides (Plan 13 Task 4) -->
    <HomeFeaturedCarousel :slides="featuredSlides" />

    <!-- 2. Cinema hero — feature film + telemetry + side panel (shown below the carousel when a movie is available) -->
    <HomeCinemaHero v-if="featuredMovie" :movie="featuredMovie" />

    <!-- 2. Now Showing — horizontal reel -->
    <HomeNowShowingReel
      v-if="nowShowingMovies.length > 0"
      :movies="nowShowingMovies"
    />

    <!-- 3. Week ahead — calendar strip -->
    <HomeCalendarStrip />

    <!-- 4. Retrospective editorial split -->
    <HomeRetrospectiveSplit />

    <!-- 5. Reel Society membership -->
    <HomeMembership />

    <!-- 6. Food & drink -->
    <HomeFoodDrink />
  </div>
</template>

