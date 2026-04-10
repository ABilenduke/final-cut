<script setup lang="ts">
import type { Movie } from '~/types/movie'

// --- SSR/ISR data ---
const { nowShowing, comingSoon } = useMovies()

const { data: nowShowingData } = nowShowing({ per_page: 8 })
const nowShowingMovies = computed(() => nowShowingData.value?.data ?? [])

const { data: comingSoonData } = comingSoon({ per_page: 6 })
const comingSoonMovies = computed(() => comingSoonData.value?.data ?? [])

// Select featured movie from listing data (pure function, ISR-safe)
const featuredMovie = computed<Movie | null>(() => selectFeaturedMovie(nowShowingMovies.value) ?? null)

// --- Handlers ---
const { show: showToast } = useToast()

function handleNotify() {
  showToast({ message: 'We\'ll let you know when tickets are available.', type: 'success' })
}

// --- SEO ---
// siteUrl must be set via NUXT_PUBLIC_SITE_URL; never derive from request on ISR pages
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
    <!-- 1. Hero: Featured now-showing film -->
    <HomeFeaturedHero v-if="featuredMovie" :movie="featuredMovie" />

    <!-- 2. Now Showing: Active browsing -->
    <section v-if="nowShowingMovies.length > 0" class="home-page__section" aria-labelledby="now-showing">
      <div class="container">
        <h2 id="now-showing" class="home-page__heading headline-lg">Now Showing</h2>
        <div class="ensemble">
          <MovieCard
            v-for="movie in nowShowingMovies"
            :key="movie.id"
            :movie="movie"
          />
        </div>
      </div>
    </section>

    <!-- 3. What's On This Week: Curated discovery -->
    <HomeEventStrip />

    <!-- 4. Coming Soon: Future intent capture -->
    <section v-if="comingSoonMovies.length > 0" class="home-page__section" aria-labelledby="coming-soon">
      <div class="container">
        <h2 id="coming-soon" class="home-page__heading headline-lg">Coming Soon</h2>
        <div class="ensemble">
          <MovieCard
            v-for="movie in comingSoonMovies"
            :key="movie.id"
            :movie="movie"
            :show-showtimes="false"
            @notify="handleNotify"
          />
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home-page__section {
  padding-block: var(--space-3xl);
}

.home-page__heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-lg);
}
</style>
