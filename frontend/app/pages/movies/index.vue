<script setup lang="ts">
type StatusFilter = 'all' | 'now_showing' | 'coming_soon'

const route = useRoute()
const router = useRouter()
const siteUrl = String(useRuntimeConfig().public.siteUrl ?? '')

const status = computed<StatusFilter>(() => {
  const s = route.query.status as string
  if (s === 'coming_soon' || s === 'all') return s
  return 'now_showing'
})

/** Location slug from URL query — undefined means "all locations" (default). */
const locationFilter = computed<string | undefined>(() => {
  const val = route.query.location
  return typeof val === 'string' && val.length > 0 ? val : undefined
})

const { nowShowing, comingSoon } = useMovies()

// Pass the locationFilter ref directly — useMovies forwards it to useFetch
// as a reactive query value + watch source so chip clicks re-fetch with the
// new slug. Each unique ?location= value still produces a distinct fetch
// key for independent ISR caching per filtered URL.
const { data: nowShowingData } = nowShowing({ location: locationFilter })
const { data: comingSoonData } = comingSoon({ location: locationFilter })

const nowShowingMovies = computed(() => nowShowingData.value?.data ?? [])
const comingSoonMovies = computed(() => comingSoonData.value?.data ?? [])

const totalCount = computed(() => nowShowingMovies.value.length + comingSoonMovies.value.length)

const FILTERS: Array<{ id: StatusFilter; label: string; count: () => number }> = [
  { id: 'all', label: 'All', count: () => totalCount.value },
  { id: 'now_showing', label: 'Now playing', count: () => nowShowingMovies.value.length },
  { id: 'coming_soon', label: 'Coming soon', count: () => comingSoonMovies.value.length },
]

function setStatus(next: StatusFilter) {
  router.replace({ query: { ...route.query, status: next } })
}

// Fetch all venues for the location chip row (SSR — memoized globally).
const { fetchLocations } = usePublicLocations()
const { data: locationsData } = fetchLocations()
const locations = computed(() => locationsData.value?.data ?? [])

// Resolve the active location name for SEO meta when a filter is applied.
const activeLocationName = computed<string | null>(() => {
  if (!locationFilter.value) return null
  return locations.value.find(l => l.slug === locationFilter.value)?.name ?? null
})

const { show: showToast } = useToast()
function handleNotify() {
  showToast({ message: "We'll let you know when tickets are available.", type: 'success' })
}

const showNowSection = computed(() => status.value === 'all' || status.value === 'now_showing')
const showSoonSection = computed(() => status.value === 'all' || status.value === 'coming_soon')

const headlineCopy = computed<{ eyebrow: string; accent: string; tail: string; lede: string }>(() => {
  if (status.value === 'coming_soon') {
    return {
      eyebrow: 'Reel · Marquee',
      accent: 'Coming',
      tail: 'soon to the screen.',
      lede: 'A short list of the films we have on the horizon — held back from the calendar until the prints are in our hands.',
    }
  }
  if (status.value === 'all') {
    return {
      eyebrow: 'Reel · Marquee',
      accent: 'On',
      tail: 'the marquee.',
      lede: 'Every film at Final Cut, top to bottom — what is playing tonight, and what we are holding the screens for next.',
    }
  }
  return {
    eyebrow: 'Reel · Marquee',
    accent: 'Now',
    tail: 'playing.',
    lede: 'Curated nightly across our two screens — small selection, longer holds, fresh prints. Pick a film, then pick your seat.',
  }
})

const pageTitle = computed(() => {
  if (activeLocationName.value) {
    if (status.value === 'coming_soon') return `Coming Soon at ${activeLocationName.value} — Final Cut`
    if (status.value === 'all') return `On the Marquee at ${activeLocationName.value} — Final Cut`
    return `Now Playing at ${activeLocationName.value} — Final Cut`
  }
  if (status.value === 'coming_soon') return 'Coming Soon — Final Cut'
  if (status.value === 'all') return 'On the Marquee — Final Cut'
  return 'Now Playing — Final Cut'
})

const pageDescription = computed(() => {
  const atLocation = activeLocationName.value ? ` at ${activeLocationName.value}` : ''
  if (status.value === 'coming_soon') return `Films coming soon to Final Cut${atLocation}. Get notified when tickets open.`
  if (status.value === 'all') return `Browse every film at Final Cut${atLocation} — now playing and coming soon.`
  return `Films now playing${atLocation ? ` at Final Cut${atLocation}` : ' at Final Cut'}. Browse showtimes and book your seat.`
})

// Canonical URL: the filtered URL is canonical for that filtered view.
// Both the location filter AND the status tab change the rendered slate,
// so each combination needs its own canonical so search engines don't
// dedupe distinct views (e.g. ?status=coming_soon vs ?status=now_showing).
// status defaults to 'now_showing' — that default URL is `/movies` (with
// any active location) to keep backlinks pointing at the same canonical.
const canonicalPath = computed(() => {
  const params = new URLSearchParams()
  if (locationFilter.value) params.set('location', locationFilter.value)
  if (status.value !== 'now_showing') params.set('status', status.value)
  const qs = params.toString()
  return qs ? `/movies?${qs}` : '/movies'
})

useHead({
  title: pageTitle,
  link: [
    { rel: 'canonical', href: computed(() => `${siteUrl}${canonicalPath.value}`) },
  ],
  meta: [
    { name: 'description', content: pageDescription },
    { property: 'og:title', content: pageTitle },
    { property: 'og:description', content: pageDescription },
    { property: 'og:type', content: 'website' },
  ],
})
</script>

<template>
  <div class="movies-page">
    <div class="container movies-page__container">
      <header class="movies-page__top">
        <div>
          <p class="movies-page__eyebrow">{{ headlineCopy.eyebrow }}</p>
          <h1 class="movies-page__title">
            <em>{{ headlineCopy.accent }}</em> {{ headlineCopy.tail }}
          </h1>
          <p class="movies-page__lede">{{ headlineCopy.lede }}</p>
        </div>
        <div class="movies-page__meta">
          <b>{{ totalCount }} films</b>
          On the marquee · updated daily
        </div>
      </header>

      <!-- Filter console — status + location grouped as one control band -->
      <div class="movies-page__controls">
        <div class="movies-page__filter">
          <span class="movies-page__filter-label">Browse</span>
          <div class="movies-page__chips" role="tablist" aria-label="Filter movies by status">
            <button
              v-for="f in FILTERS"
              :key="f.id"
              type="button"
              class="movies-page__chip"
              :class="{ 'movies-page__chip--on': status === f.id }"
              role="tab"
              :aria-selected="status === f.id"
              @click="setStatus(f.id)"
            >
              <span>{{ f.label }}</span>
              <span class="movies-page__chip-count">{{ String(f.count()).padStart(2, '0') }}</span>
            </button>
          </div>
        </div>

        <!-- Location filter chips (opt-in cross-location narrowing) -->
        <div v-if="locations.length > 0" class="movies-page__location-filter">
          <span class="movies-page__filter-label">Cinema</span>
          <LocationFilterChips :locations="locations" />
        </div>
      </div>

      <!-- Now Playing section -->
      <section v-if="showNowSection" class="movies-page__section">
        <header class="movies-page__sec-head">
          <div>
            <span class="movies-page__sec-n">§ 01 · Now playing</span>
            <h2 class="movies-page__sec-title">
              <em>Tonight</em> on the screens.
            </h2>
          </div>
          <p class="movies-page__sec-info">
            <b>{{ nowShowingMovies.length }}</b> film{{ nowShowingMovies.length === 1 ? '' : 's' }}
          </p>
        </header>

        <div v-if="nowShowingMovies.length > 0" class="movies-page__grid">
          <MovieCard
            v-for="(movie, idx) in nowShowingMovies"
            :key="movie.id"
            :movie="movie"
            :show-showtimes="true"
            :index="idx + 1"
            @notify="handleNotify"
          />
        </div>
        <p v-else class="movies-page__empty">
          The marquee is dark tonight. Check the calendar for special programmes.
        </p>
      </section>

      <!-- Coming Soon section -->
      <section v-if="showSoonSection" class="movies-page__section">
        <header class="movies-page__sec-head">
          <div>
            <span class="movies-page__sec-n">§ {{ status === 'all' ? '02' : '01' }} · Coming soon</span>
            <h2 class="movies-page__sec-title">
              <em>Held</em> for the next reel.
            </h2>
          </div>
          <p class="movies-page__sec-info">
            <b>{{ comingSoonMovies.length }}</b> film{{ comingSoonMovies.length === 1 ? '' : 's' }}
          </p>
        </header>

        <div v-if="comingSoonMovies.length > 0" class="movies-page__grid">
          <MovieCard
            v-for="(movie, idx) in comingSoonMovies"
            :key="movie.id"
            :movie="movie"
            :show-showtimes="false"
            :index="idx + 1"
            @notify="handleNotify"
          />
        </div>
        <p v-else class="movies-page__empty">
          Nothing on the horizon yet. We'll post the next set of holds soon.
        </p>
      </section>
    </div>
  </div>
</template>

<style scoped>
.movies-page {
  padding: var(--space-2xl) 0 var(--space-4xl);
}

.movies-page__container {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

/* Page top */
.movies-page__top {
  padding: 0 0 var(--space-lg);
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-lg);
  flex-wrap: wrap;
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.movies-page__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0 0 0.5rem;
}

.movies-page__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.movies-page__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.25rem);
  line-height: 1;
  letter-spacing: -0.03em;
  text-wrap: balance;
  max-width: 18ch;
  color: var(--on-surface);
  margin: 0;
}

.movies-page__title em {
  font-style: italic;
  color: var(--tertiary);
}

.movies-page__lede {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1rem;
  max-width: 52ch;
  margin: var(--space-md) 0 0;
  line-height: 1.55;
  font-style: italic;
}

.movies-page__meta {
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
}

.movies-page__meta b {
  font-family: var(--font-display);
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.04em;
  font-size: 0.9375rem;
  text-transform: none;
  display: block;
  margin-bottom: 0.2rem;
}

/* Filter console — status + location rows packed as one control band,
   closed by a single editorial rule (no per-row borders). */
.movies-page__controls {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  padding-bottom: var(--space-lg);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.movies-page__filter,
.movies-page__location-filter {
  display: flex;
  align-items: flex-start;
  gap: var(--space-md);
  flex-wrap: wrap;
}

/* Fixed-width label rail with a hairline divider — both rows align to the
   same x so the chips read as a single instrument cluster. */
.movies-page__filter-label {
  flex-shrink: 0;
  min-width: 4.5rem;
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding-top: 0.6rem; /* optical: align label with the first chip row's text */
  padding-right: var(--space-sm);
  border-right: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
}

.movies-page__chips {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.movies-page__chip {
  padding: 0.5rem 0.9rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.45);
  border-radius: 999px; /* token-exception: editorial pill — same as catalog chips */
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--tertiary);
  background-color: var(--surface-container-low);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard);
}

.movies-page__chip:hover {
  border-color: rgb(var(--secondary-rgb) / 0.4);
  color: var(--on-surface);
}

.movies-page__chip--on {
  border-color: var(--secondary);
  color: var(--secondary);
  background-color: rgb(var(--secondary-rgb) / 0.06);
}

.movies-page__chip-count {
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
  font-size: 0.6875rem;
  color: var(--on-tertiary-fixed-variant);
  padding-left: 0.35rem;
  border-left: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
}

.movies-page__chip--on .movies-page__chip-count {
  color: var(--secondary);
  border-left-color: rgb(var(--secondary-rgb) / 0.35);
}

/* Section */
.movies-page__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.movies-page__sec-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-md);
  padding-bottom: var(--space-md);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.movies-page__sec-n {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
  display: block;
  margin-bottom: 0.35rem;
}

.movies-page__sec-title {
  font-family: var(--font-display);
  font-size: 1.625rem;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.1;
  margin: 0;
  color: var(--on-surface);
}

.movies-page__sec-title em {
  font-style: italic;
  color: var(--tertiary);
}

.movies-page__sec-info {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin: 0;
  text-align: right;
}

.movies-page__sec-info b {
  color: var(--tertiary);
  font-family: var(--font-display);
  font-weight: 500;
  letter-spacing: 0.04em;
  font-size: 0.9375rem;
}

/* Grid */
.movies-page__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-md);
}

.movies-page__empty {
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  padding: var(--space-lg) 0;
  margin: 0;
}

@media (max-width: 80rem) {
  .movies-page__grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 60rem) {
  .movies-page__grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 40rem) {
  .movies-page__grid {
    grid-template-columns: 1fr;
  }
}
</style>
