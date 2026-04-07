<script setup lang="ts">
import type { Movie } from '~/types/movie'
import type { Showtime } from '~/types/showtime'
import { useApiFetch, apiFetch } from '~/utils/api'

// --- SSR/ISR data ---
const { nowShowing, comingSoon } = useMovies()

const { data: nowShowingData } = nowShowing({ per_page: 8 })
const nowShowingMovies = computed(() => nowShowingData.value?.data ?? [])

const { data: comingSoonData } = comingSoon({ per_page: 6 })
const comingSoonMovies = computed(() => comingSoonData.value?.data ?? [])

// Select featured movie from listing data (pure function, ISR-safe)
const featuredListing = computed(() => selectFeaturedMovie(nowShowingMovies.value))

// Fetch full detail for featured movie (listing may omit tagline/synopsis).
// useApiFetch accepts Ref<string> and refetches when the path changes.
const featuredDetailUrl = computed(() =>
  featuredListing.value ? `/api/movies/${featuredListing.value.slug}` : '',
)
const { data: featuredDetailData } = useApiFetch<{ data: Movie }>(featuredDetailUrl)
const featuredMovie = computed<Movie | null>(() => {
  // Prefer detail data; fall back to listing data while detail loads
  return featuredDetailData.value?.data ?? featuredListing.value ?? null
})

// --- Client-only: hero showtime for CTA ---
const { activeLocation } = useLocations()
const locationSelected = computed(() => !!activeLocation.value)
const heroShowtime = ref<Showtime | null>(null)
const heroLoading = ref(false)
let fetchGeneration = 0 // Guards against stale async responses

async function fetchHeroShowtime() {
  const locSlug = activeLocation.value?.slug
  const movSlug = featuredMovie.value?.slug
  if (!locSlug || !movSlug) {
    heroShowtime.value = null
    return
  }
  const generation = ++fetchGeneration
  heroLoading.value = true
  try {
    const res = await apiFetch<{ data: Showtime[] }>(
      `/api/locations/${locSlug}/movies/${movSlug}/showtimes`,
    )
    // Only commit if this is still the latest request
    if (generation === fetchGeneration) {
      heroShowtime.value = res.data[0] ?? null
    }
  } catch {
    if (generation === fetchGeneration) {
      heroShowtime.value = null
    }
  } finally {
    if (generation === fetchGeneration) {
      heroLoading.value = false
    }
  }
}

if (import.meta.client) {
  watch([activeLocation, featuredMovie], () => fetchHeroShowtime(), { immediate: true })
}

// --- Handlers ---
const { show: showToast } = useToast()

function handleNotify() {
  showToast({ message: 'We\'ll let you know when tickets are available.', type: 'success' })
}

function openLocationSelector() {
  // Focus the SiteHeader's location <select> to prompt the user to pick a location
  document.getElementById('location-select')?.focus()
}

// --- SEO ---
useHead({
  title: 'Final Cut \u2014 Now Showing & Tickets',
  meta: [
    {
      name: 'description',
      content: 'Now showing at Final Cut. Get tickets, browse showtimes, and discover upcoming events.',
    },
  ],
})
</script>

<template>
  <div class="home-page">
    <!-- 1. Hero: Immediate conversion -->
    <HomeFeaturedHero
      v-if="featuredMovie"
      :movie="featuredMovie"
      :next-showtime="heroShowtime"
      :location-selected="locationSelected"
      :loading="heroLoading"
      @choose-location="openLocationSelector"
    />

    <!-- 2. Quick Showtime Strip: Intent-first fast path -->
    <QuickShowtimeStrip :location="activeLocation" @change-location="openLocationSelector" />

    <!-- 3. Now Showing: Active browsing -->
    <section v-if="nowShowingMovies.length > 0" class="home-page__section" aria-labelledby="now-showing">
      <div class="container">
        <h2 id="now-showing" class="home-page__heading headline-lg">Now Showing</h2>
        <div class="ensemble">
          <MovieCard
            v-for="movie in nowShowingMovies"
            :key="movie.id"
            :movie="movie"
            variant="now-showing"
          />
        </div>
      </div>
    </section>

    <!-- 4. What's On This Week: Curated discovery -->
    <HomeEventStrip />

    <!-- 5. Coming Soon: Future intent capture -->
    <section v-if="comingSoonMovies.length > 0" class="home-page__section" aria-labelledby="coming-soon">
      <div class="container">
        <h2 id="coming-soon" class="home-page__heading headline-lg">Coming Soon</h2>
        <div class="ensemble">
          <MovieCard
            v-for="movie in comingSoonMovies"
            :key="movie.id"
            :movie="movie"
            variant="coming-soon"
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
