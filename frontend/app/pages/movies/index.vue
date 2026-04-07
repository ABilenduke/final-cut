<script setup lang="ts">
// Read URL query params for state
const route = useRoute()

const status = computed(() => {
  const s = route.query.status as string
  return s === 'coming_soon' ? 'coming_soon' : 'now_showing'
})

// Fetch movies based on active tab
const { nowShowing, comingSoon } = useMovies()

// Use the appropriate composable based on status
// Since useApiFetch (useFetch) needs to be called at setup time,
// we fetch both and show the active one
const { data: nowShowingData } = nowShowing()
const { data: comingSoonData } = comingSoon()

const movies = computed(() => {
  if (status.value === 'coming_soon') {
    return comingSoonData.value?.data ?? []
  }
  return nowShowingData.value?.data ?? []
})

const cardVariant = computed(() =>
  status.value === 'coming_soon' ? 'coming-soon' as const : 'now-showing' as const
)

// Tab navigation
function setStatus(newStatus: 'now_showing' | 'coming_soon') {
  navigateTo({ query: { ...route.query, status: newStatus } })
}

// TODO: Genre filtering (?genre=28) — deferred, add filter controls when needed

// Notify Me handler for Coming Soon cards
const { show: showToast } = useToast()
function handleNotify() {
  showToast({ message: "We'll let you know when tickets are available.", type: 'success' })
}

// SEO
const pageTitle = computed(() =>
  status.value === 'coming_soon' ? 'Coming Soon — Final Cut' : 'Now Showing — Final Cut'
)
const pageDescription = computed(() =>
  status.value === 'coming_soon'
    ? 'Browse coming soon movies at Final Cut. Find showtimes and get tickets.'
    : 'Browse now showing movies at Final Cut. Find showtimes and get tickets.'
)

useHead({
  title: pageTitle,
  meta: [{ name: 'description', content: pageDescription }],
})
</script>

<template>
  <div class="movies-page">
    <div class="movies-page__container">
      <!-- Tab bar -->
      <div class="movies-page__tabs" role="tablist" aria-label="Movie status">
        <button
          id="tab-now-showing"
          role="tab"
          :aria-selected="status === 'now_showing'"
          aria-controls="panel-movies"
          :tabindex="status === 'now_showing' ? 0 : -1"
          class="movies-page__tab headline-sm"
          :class="{ 'movies-page__tab--active': status === 'now_showing' }"
          @click="setStatus('now_showing')"
        >
          Now Showing
        </button>
        <button
          id="tab-coming-soon"
          role="tab"
          :aria-selected="status === 'coming_soon'"
          aria-controls="panel-movies"
          :tabindex="status === 'coming_soon' ? 0 : -1"
          class="movies-page__tab headline-sm"
          :class="{ 'movies-page__tab--active': status === 'coming_soon' }"
          @click="setStatus('coming_soon')"
        >
          Coming Soon
        </button>
      </div>

      <!-- Movie grid -->
      <div
        id="panel-movies"
        role="tabpanel"
        :aria-labelledby="status === 'now_showing' ? 'tab-now-showing' : 'tab-coming-soon'"
        class="ensemble"
      >
        <MovieCard
          v-for="movie in movies"
          :key="movie.id"
          :movie="movie"
          :variant="cardVariant"
          @notify="handleNotify"
        />
      </div>

      <!-- Empty state -->
      <p v-if="movies.length === 0" class="movies-page__empty body-md">
        No movies found.
      </p>
    </div>
  </div>
</template>

<style scoped>
.movies-page {
  padding-block: var(--space-3xl);
}

.movies-page__container {
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-2xl);
}

.movies-page__tabs {
  display: flex;
  gap: var(--space-md);
  margin-bottom: var(--space-xl);
}

.movies-page__tab {
  background: none;
  border: none;
  color: var(--tertiary);
  cursor: pointer;
  padding: var(--space-xs) 0;
  position: relative;
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  transition: color var(--duration-micro) var(--ease-standard);
}

.movies-page__tab:hover {
  color: var(--on-surface);
}

.movies-page__tab--active {
  color: var(--on-surface);
}

.movies-page__tab--active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 0.125rem;
  background-color: var(--secondary);
}

.movies-page__tab:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.movies-page__empty {
  color: var(--tertiary);
  text-align: center;
  padding: var(--space-3xl) 0;
}

@media (max-width: 60rem) {
  .movies-page__container {
    padding-inline: var(--space-md);
  }
}
</style>
