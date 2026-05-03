<script setup lang="ts">
import type { Location } from '~/types/location'

const { fetchLocations } = usePublicLocations()
const { data, error } = fetchLocations()

const locations = computed<Location[]>(() => data.value?.data ?? [])

// SEO
const siteUrl = useRuntimeConfig().public.siteUrl as string

useHead({
  title: 'Our Cinemas — Final Cut',
  link: [
    { rel: 'canonical', href: siteUrl ? `${siteUrl}/locations` : undefined },
  ],
})

useSeoMeta({
  description: 'Visit Final Cut — two cinemas, one obsession. Find addresses, hours, and showtimes for all our locations.',
  ogTitle: 'Our Cinemas — Final Cut',
  ogDescription: 'Visit Final Cut — two cinemas, one obsession. Find addresses, hours, and showtimes for all our locations.',
  ogType: 'website',
  ogUrl: siteUrl ? `${siteUrl}/locations` : undefined,
  twitterCard: 'summary_large_image',
  twitterTitle: 'Our Cinemas — Final Cut',
  twitterDescription: 'Visit Final Cut — two cinemas, one obsession. Find addresses, hours, and showtimes for all our locations.',
})

// Structured data: ItemList of LocalBusiness references
useHead({
  script: [
    {
      type: 'application/ld+json',
      innerHTML: computed(() => safeJsonLd({
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: 'Final Cut Cinemas',
        itemListElement: locations.value.map((loc, i) => ({
          '@type': 'ListItem',
          position: i + 1,
          item: {
            '@type': 'LocalBusiness',
            name: loc.name,
            ...(siteUrl ? { url: `${siteUrl}/locations/${loc.slug}` } : {}),
            address: {
              '@type': 'PostalAddress',
              streetAddress: loc.street,
              addressLocality: loc.city,
              addressRegion: loc.state,
              postalCode: loc.postal_code,
              addressCountry: loc.country,
            },
            ...(loc.phone ? { telephone: loc.phone } : {}),
            ...(loc.latitude != null && loc.longitude != null ? {
              geo: {
                '@type': 'GeoCoordinates',
                latitude: loc.latitude,
                longitude: loc.longitude,
              },
            } : {}),
          },
        })),
      })),
    },
  ],
})
</script>

<template>
  <div class="locations-page">

    <!-- ——— Hero ——— -->
    <section class="locations-page__hero wide-frame" aria-labelledby="locations-hero-heading">
      <div class="locations-page__hero-bg" aria-hidden="true" />
      <div class="locations-page__hero-inner wide-frame__content">
        <p class="locations-page__hero-eyebrow" aria-hidden="true">Final Cut · Cinemas</p>
        <h1 id="locations-hero-heading" class="locations-page__hero-title">
          Two cinemas,<br><em>one obsession.</em>
        </h1>
        <p class="locations-page__hero-lede">
          Each screen at Final Cut was selected for its character and its community.
          Same programme. Different neighbourhood.
        </p>
      </div>
    </section>

    <!-- ——— Locations Grid ——— -->
    <div class="container locations-page__body">

      <!-- Error state -->
      <p v-if="error" class="locations-page__error" role="alert">
        We couldn't load our locations right now. Please try again shortly.
      </p>

      <!-- Ensemble grid -->
      <div
        v-else
        class="locations-page__grid"
        aria-label="Cinema locations"
      >
        <LocationCard
          v-for="location in locations"
          :key="location.slug"
          :location="location"
        />

        <!-- Empty state while data is loading -->
        <template v-if="locations.length === 0 && !error">
          <div
            v-for="n in 2"
            :key="n"
            class="locations-page__skeleton"
            aria-hidden="true"
          />
        </template>
      </div>

      <!-- Editorial closer -->
      <aside class="locations-page__editorial">
        <p class="locations-page__editorial-copy">
          We take our time finding the right building — not just the right footprint.
          If you'd like to enquire about future expansion, <NuxtLink to="/contact" class="locations-page__editorial-link">get in touch</NuxtLink>.
        </p>
      </aside>

    </div>
  </div>
</template>

<style scoped>
.locations-page {
  padding-bottom: var(--space-4xl);
}

/* ——— Hero ——— */
.locations-page__hero {
  position: relative;
  min-height: 22rem;
  overflow: hidden;
  display: flex;
  align-items: flex-end;
  margin-bottom: var(--space-3xl);
}

.locations-page__hero-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    radial-gradient(
      ellipse 80% 90% at 20% 70%,
      rgba(var(--primary-container-rgb), 0.45),
      transparent 60%
    ),
    linear-gradient(
      180deg,
      var(--surface-container) 0%,
      var(--surface-container-lowest) 100%
    );
}

.locations-page__hero-inner {
  position: relative;
  z-index: 1;
  padding-top: var(--space-3xl);
  padding-bottom: var(--space-2xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.locations-page__hero-eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin: 0;
}

.locations-page__hero-title {
  font-family: var(--font-display);
  font-size: clamp(2.25rem, 5vw, 3.5rem);
  font-weight: 500;
  letter-spacing: -0.03em;
  line-height: 1;
  color: var(--on-surface);
  margin: 0;
}

.locations-page__hero-title em {
  font-style: italic;
  color: var(--tertiary);
}

.locations-page__hero-lede {
  font-family: var(--font-body);
  font-size: var(--type-body-md, 1rem);
  color: var(--tertiary);
  margin: 0;
  max-width: 44ch;
  line-height: 1.6;
  font-style: italic;
}

/* ——— Body ——— */
.locations-page__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-3xl);
}

/* ——— Grid ——— */
.locations-page__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(17.5rem, 1fr));
  gap: var(--space-lg);
}

/* ——— Skeleton placeholders ——— */
.locations-page__skeleton {
  background-color: var(--surface-container-low);
  border-radius: var(--radius-card);
  min-height: 22rem;
  animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
  0%, 100% { opacity: 0.6; }
  50%       { opacity: 0.9; }
}

@media (prefers-reduced-motion: reduce) {
  .locations-page__skeleton {
    animation: none;
  }
}

/* ——— Error ——— */
.locations-page__error {
  font-family: var(--font-body);
  font-size: var(--type-body-md, 1rem);
  color: var(--state-danger-text);
  padding: var(--space-lg);
  background-color: var(--surface-container-low);
  border-radius: var(--radius-card);
  margin: 0;
}

/* ——— Editorial closer ——— */
.locations-page__editorial {
  padding: var(--space-lg) 0;
  border-top: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
}

.locations-page__editorial-copy {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  line-height: 1.6;
  margin: 0;
  max-width: 60ch;
}

.locations-page__editorial-link {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.2em;
  transition: color var(--duration-micro) var(--ease-standard);
}

.locations-page__editorial-link:hover {
  color: var(--secondary-hover);
}

.locations-page__editorial-link:focus-visible {
  outline: var(--border-thick) solid var(--secondary);
  outline-offset: 0.125rem;
  border-radius: var(--radius-sm);
}
</style>
