<script setup lang="ts">
/**
 * /food-drink — Cross-location food & drink browse page.
 *
 * Rendering: ISR (routeRules: /food-drink → isr: 1800)
 *
 * Data: single fetch from /api/food-menu (no location segment).
 * The shared endpoint returns every item across all venues; each item
 * carries available_at: string[] so this page can render availability
 * captions for items stocked at only a subset of venues.
 *
 * Items with available_at: [] are excluded before render — they are
 * administratively unavailable and should not appear on the public page.
 *
 * Venue roster from usePublicLocations is fetched in parallel so the
 * ConcessionsCatalog can compute captions like "Available at Downtown only".
 */

const { fetchAll } = useFoodMenu()
const { fetchLocations } = usePublicLocations()

// SSR-parallel data fetches. Both use useFetch under the hood so Nuxt
// deduplicates concurrent calls and hydrates correctly on the client.
const { data: menuData } = await fetchAll()
const { data: locationsData } = await fetchLocations()

// Filter out items with no availability (available_at: []). Items with
// no available_at field (legacy / per-location path) are shown without caption.
const items = computed(() => {
  const raw = menuData.value?.data ?? []
  return raw.filter(item => {
    if (!item.available_at) return true          // no field → show normally
    return item.available_at.length > 0          // empty array → exclude
  })
})

// Venue roster for availability captions. Shape: { slug, name }[]
const venues = computed(() =>
  (locationsData.value?.data ?? []).map(loc => ({
    slug: loc.slug,
    name: loc.name,
  })),
)

const config = useRuntimeConfig()
const siteUrl = config.public.siteUrl ?? ''
const canonicalUrl = `${siteUrl}/food-drink`

useHead({
  title: 'Food & Drink — Final Cut',
  link: [
    { rel: 'canonical', href: canonicalUrl },
  ],
})

useSeoMeta({
  description: 'Curated bar and concessions menu — small selection, better quality. Pre-order with your tickets.',
  ogTitle: 'Food & Drink — Final Cut',
  ogDescription: 'Curated bar and concessions menu — small selection, better quality. Pre-order with your tickets.',
  ogType: 'website',
  ogUrl: canonicalUrl,
  twitterCard: 'summary_large_image',
  twitterTitle: 'Food & Drink — Final Cut',
  twitterDescription: 'Curated bar and concessions menu — small selection, better quality. Pre-order with your tickets.',
})
</script>

<template>
  <div class="food-drink-page">
    <div class="container food-drink-page__container">
      <header class="food-drink-page__top">
        <div>
          <p class="food-drink-page__eyebrow">Reel · Concessions</p>
          <h1 class="food-drink-page__title">
            <em>The bar &amp;</em> snack counter.
          </h1>
          <p class="food-drink-page__lede">
            Curated by the programming team — small selection, better quality.
            Browse the counter; pre-order with your tickets when you book.
          </p>
        </div>
      </header>

      <ConcessionsCatalog
        :items="items"
        :venues="venues"
        :interactive="false"
      />

      <ConcessionsAllergenNotice />

      <ConcessionsCollectionInfo />

      <!--
        FTC/UX footer note: availability varies by location.
        Keeps the browse experience clean while setting accurate expectations.
      -->
      <p class="food-drink-page__availability-note">
        Selection may vary by location. The full picture lives in your booking confirmation.
      </p>
    </div>
  </div>
</template>

<style scoped>
.food-drink-page {
  padding: var(--space-3xl) 0 var(--space-4xl);
}

.food-drink-page__container {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.food-drink-page__top {
  padding: var(--space-xl) 0 var(--space-lg);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.food-drink-page__eyebrow {
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

.food-drink-page__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.food-drink-page__title {
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

.food-drink-page__title em {
  font-style: italic;
  color: var(--tertiary);
}

.food-drink-page__lede {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1rem;
  max-width: 52ch;
  margin: var(--space-md) 0 0;
  line-height: 1.55;
  font-style: italic;
}

.food-drink-page__availability-note {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.06em;
  font-style: italic;
  margin: 0;
  padding-top: var(--space-lg);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}
</style>
