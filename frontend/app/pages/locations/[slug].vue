<script setup lang="ts">
import type { Movie } from '~/types/movie'
import type { CalendarEvent } from '~/types/calendar-event'

const route = useRoute()
const slug = computed(() => route.params.slug as string)

// ——— Primary venue data ———
// Awaited so SSR can act on the resolved error before continuing setup.
// Without `await`, `locationError.value` is still null when the gate below
// runs and Nitro returns 200 for unknown slugs.
const { fetchBySlug } = usePublicLocations()
const { data: locationData, error: locationError } = await fetchBySlug(slug.value)
const location = computed(() => locationData.value?.data ?? null)

// ——— 404 gate ———
if (locationError.value) {
  throw createError({
    statusCode: 404,
    statusMessage: 'Location not found',
    fatal: true,
  })
}

// ——— Now Showing Here ———
const { nowShowing } = useMovies()
const { data: moviesData } = nowShowing({ location: slug.value } as any)
const nowShowingMovies = computed<Movie[]>(() => moviesData.value?.data ?? [])

// ——— Upcoming Events ———
const { getEvents } = useCalendarEvents()
const today = new Date()
const { data: eventsData } = getEvents(today.getMonth() + 1, today.getFullYear())
// Client-side filter: keep events at this location or venue-agnostic events, first 5
const upcomingEvents = computed<CalendarEvent[]>(() =>
  (eventsData.value?.data ?? [])
    .filter((e) => e.type !== 'showtime')
    .slice(0, 5),
)

// ——— SEO ———
const siteUrl = useRuntimeConfig().public.siteUrl as string

const pageTitle = computed(() =>
  location.value ? `${location.value.name} — Final Cut` : 'Location — Final Cut',
)

const pageDescription = computed(() => {
  if (!location.value) return 'Final Cut cinema location details, hours, and showtimes.'
  const { name, street, city } = location.value
  return `${name} at ${street}, ${city}. Hours, directions, accessibility info, and now-showing films at Final Cut.`
})

const canonicalUrl = computed(() =>
  siteUrl ? `${siteUrl}/locations/${slug.value}` : undefined,
)

useHead({
  title: pageTitle,
  link: [
    { rel: 'canonical', href: canonicalUrl },
  ],
})

useSeoMeta({
  description: pageDescription,
  ogTitle: pageTitle,
  ogDescription: pageDescription,
  ogType: 'website',
  ogUrl: canonicalUrl,
  ogImage: computed(() => location.value?.imageUrl ?? undefined),
  twitterCard: 'summary_large_image',
  twitterTitle: pageTitle,
  twitterDescription: pageDescription,
})

// ——— LocalBusiness JSON-LD ———
useHead({
  script: [
    {
      type: 'application/ld+json',
      innerHTML: computed(() => {
        if (!location.value) return 'null'
        const loc = location.value
        const schema: Record<string, unknown> = {
          '@context': 'https://schema.org',
          '@type': 'LocalBusiness',
          '@id': canonicalUrl.value ?? `/locations/${slug.value}`,
          name: loc.name,
          ...(siteUrl ? { url: canonicalUrl.value } : {}),
          address: {
            '@type': 'PostalAddress',
            streetAddress: loc.street,
            addressLocality: loc.city,
            addressRegion: loc.state,
            postalCode: loc.postal_code,
            addressCountry: loc.country,
          },
          ...(loc.phone ? { telephone: loc.phone } : {}),
          ...(loc.email ? { email: loc.email } : {}),
          ...(loc.latitude != null && loc.longitude != null ? {
            geo: {
              '@type': 'GeoCoordinates',
              latitude: loc.latitude,
              longitude: loc.longitude,
            },
          } : {}),
          ...(loc.imageUrl ? { image: loc.imageUrl } : {}),
          priceRange: '$$',
          // Hours of operation
          ...(loc.hours && Object.keys(loc.hours).length > 0 ? {
            openingHoursSpecification: Object.entries(loc.hours)
              .filter(([, v]) => v !== null)
              .map(([day, hours]) => ({
                '@type': 'OpeningHoursSpecification',
                dayOfWeek: `https://schema.org/${day.charAt(0).toUpperCase() + day.slice(1)}`,
                opens: hours!.open,
                closes: hours!.close,
              })),
          } : {}),
          // BreadcrumbList
          breadcrumb: {
            '@type': 'BreadcrumbList',
            itemListElement: [
              { '@type': 'ListItem', position: 1, name: 'Home', item: siteUrl || '/' },
              { '@type': 'ListItem', position: 2, name: 'Cinemas', item: siteUrl ? `${siteUrl}/locations` : '/locations' },
              { '@type': 'ListItem', position: 3, name: loc.name, item: canonicalUrl.value ?? `/locations/${slug.value}` },
            ],
          },
        }
        return safeJsonLd(schema)
      }),
    },
  ],
})
</script>

<template>
  <div class="location-detail-page">

    <!-- ——— Loading / Not Found states ——— -->
    <template v-if="!location && !locationError">
      <div class="location-detail-page__loading container" aria-live="polite" aria-busy="true">
        <div class="location-detail-page__skeleton-hero" aria-hidden="true" />
      </div>
    </template>

    <template v-else-if="locationError || !location">
      <div class="container location-detail-page__not-found">
        <h1 class="location-detail-page__not-found-title">Location not found</h1>
        <p class="location-detail-page__not-found-body">
          We couldn't find that cinema. <NuxtLink to="/locations" class="location-detail-page__link">View all our locations</NuxtLink>.
        </p>
      </div>
    </template>

    <!-- ——— Main content ——— -->
    <template v-else>
      <!-- Wide Frame hero -->
      <LocationHero :location="location" />

      <!-- Establishing Shot 65/35 -->
      <div class="container location-detail-page__body">
        <div class="establishing-shot">

          <!-- Left 65%: venue info -->
          <main id="main-content" class="location-detail-page__main" tabindex="-1">
            <LocationDetailPanel :location="location" />
          </main>

          <!-- Right 35%: Now Showing + Upcoming Events -->
          <aside class="location-detail-page__sidebar" aria-label="What's on here">

            <!-- Now Showing Here -->
            <section
              class="location-detail-page__strip"
              aria-labelledby="now-showing-heading"
            >
              <h2 id="now-showing-heading" class="location-detail-page__strip-title">
                Now Showing Here
              </h2>

              <div v-if="nowShowingMovies.length > 0" class="location-detail-page__movie-strip">
                <MovieCard
                  v-for="movie in nowShowingMovies.slice(0, 4)"
                  :key="movie.id"
                  :movie="movie"
                  :show-showtimes="true"
                />
              </div>
              <p v-else class="location-detail-page__strip-empty">
                Check back soon — the next programme is being set.
              </p>
            </section>

            <!-- Upcoming Events -->
            <section
              v-if="upcomingEvents.length > 0"
              class="location-detail-page__strip"
              aria-labelledby="upcoming-events-heading"
            >
              <h2 id="upcoming-events-heading" class="location-detail-page__strip-title">
                Upcoming Events
              </h2>

              <div class="location-detail-page__event-list">
                <EventListCard
                  v-for="event in upcomingEvents"
                  :key="event.id"
                  :event="event"
                />
              </div>
            </section>

            <!-- Directions CTA -->
            <div class="location-detail-page__cta-cluster">
              <a
                v-if="location.latitude != null && location.longitude != null"
                :href="`https://maps.google.com/?q=${location.latitude},${location.longitude}`"
                target="_blank"
                rel="noopener noreferrer"
                class="location-detail-page__btn-primary"
                :aria-label="`Get directions to ${location.name}`"
              >
                Get Directions
              </a>
              <NuxtLink
                :to="`/movies?location=${location.slug}`"
                class="location-detail-page__btn-secondary"
              >
                See All Showtimes
              </NuxtLink>
            </div>

          </aside>
        </div>
      </div>
    </template>

  </div>
</template>

<style scoped>
.location-detail-page {
  padding-bottom: var(--space-4xl);
}

/* ——— Loading skeleton ——— */
.location-detail-page__loading {
  padding-top: var(--space-3xl);
}

.location-detail-page__skeleton-hero {
  height: 24rem;
  background-color: var(--surface-container-low);
  border-radius: var(--radius-card);
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  0%, 100% { opacity: 0.6; }
  50%       { opacity: 0.9; }
}

@media (prefers-reduced-motion: reduce) {
  .location-detail-page__skeleton-hero {
    animation: none;
  }
}

/* ——— Not found ——— */
.location-detail-page__not-found {
  padding: var(--space-3xl) 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.location-detail-page__not-found-title {
  font-family: var(--font-display);
  font-size: clamp(1.5rem, 3vw, 2.25rem);
  font-weight: 500;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin: 0;
}

.location-detail-page__not-found-body {
  font-family: var(--font-body);
  font-size: var(--type-body-md, 1rem);
  color: var(--tertiary);
  margin: 0;
}

.location-detail-page__link {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.location-detail-page__link:hover {
  color: var(--secondary-hover);
}

/* ——— Main layout ——— */
.location-detail-page__body {
  padding-top: var(--space-2xl);
}

/* focus ring for skip-nav target */
.location-detail-page__main:focus-visible {
  outline: var(--border-thick) solid var(--secondary);
  outline-offset: 0.25rem;
  border-radius: var(--radius-sm);
}

/* ——— Sidebar ——— */
.location-detail-page__sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

/* ——— Strip sections ——— */
.location-detail-page__strip {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.location-detail-page__strip-title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm, 1.5rem);
  font-weight: 500;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin: 0;
  padding-bottom: var(--space-sm);
  border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
}

.location-detail-page__strip-empty {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  margin: 0;
}

/* ——— Movie strip ——— */
.location-detail-page__movie-strip {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

/* ——— Event list ——— */
.location-detail-page__event-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

/* ——— CTA cluster ——— */
.location-detail-page__cta-cluster {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.location-detail-page__btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: var(--control-height);
  padding: 0 var(--space-md);
  background-color: var(--primary-container);
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  font-weight: 500;
  text-decoration: none;
  border-radius: var(--radius-sm);
  transition: background-color var(--duration-micro) var(--ease-standard);
}

.location-detail-page__btn-primary:hover {
  background-color: var(--primary-container-hover);
}

.location-detail-page__btn-primary:focus-visible {
  outline: var(--border-thick) solid var(--secondary);
  outline-offset: 0.125rem;
}

.location-detail-page__btn-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: var(--control-height);
  padding: 0 var(--space-md);
  background-color: var(--surface-container-high);
  color: var(--on-surface);
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  text-decoration: none;
  border-radius: var(--radius-sm);
  transition: background-color var(--duration-micro) var(--ease-standard);
}

.location-detail-page__btn-secondary:hover {
  background-color: var(--surface-container);
}

.location-detail-page__btn-secondary:focus-visible {
  outline: var(--border-thick) solid var(--secondary);
  outline-offset: 0.125rem;
}
</style>
