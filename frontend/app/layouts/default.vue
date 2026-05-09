<script setup lang="ts">
const tickerItems = [
  { label: 'Now Showing', text: 'Dune: Part Three · Screen 01 · 7:30' },
  { label: 'Event', text: 'Kubrick Retrospective · Fri 9:00 PM' },
  { label: 'Members', text: 'Bar opens 60 min before all screenings' },
  { label: 'Reel 0047', text: 'Projection: 70mm · Auditorium 03' },
  { label: 'Weather', text: 'Clear · 12°C · Valet open' },
  { label: 'Arrivals', text: 'The Cold Dawn · Fri' },
  { label: 'Late Show', text: 'Mulholland Drive · Sun 11:00 PM' },
  { label: 'Food', text: 'Director\u2019s Flight · paired nightly' },
  { label: 'Loyalty', text: 'Charter enrolment open through April' },
]

// Pages can suppress the global NeuralTicker via `definePageMeta({ hideTicker: true })`.
// Used by /gift-cards, where a page-specific balance-lookup strip occupies the same
// chrome slot as the ticker would.
const route = useRoute()
const showTicker = computed(() => !route.meta.hideTicker)
</script>

<template>
  <div class="layout-default">
    <SkipNav />
    <div class="layout-default__chrome">
      <SiteHeader />
      <NeuralTicker v-if="showTicker" :items="tickerItems" />
    </div>

    <main id="main-content" tabindex="-1" class="layout-default__main">
      <slot />
    </main>

    <SiteFooter />
    <MobileNav />
    <CvToastContainer />
  </div>
</template>

<style scoped>
.layout-default {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

/* Header + ticker stick together as one chrome unit. Sticky positioning
 * means the wrapper takes its natural height in document flow, so the
 * main slot doesn't need a hardcoded padding-top to compensate — content
 * lands at the right place automatically regardless of header/ticker
 * height changes. */
.layout-default__chrome {
  position: sticky;
  top: 0;
  z-index: var(--z-sticky);
}

.layout-default__main {
  flex: 1;
  /* Offset for mobile bottom nav on small screens */
  padding-bottom: calc(3.5rem + env(safe-area-inset-bottom, 0rem));
}

@media (min-width: 60rem) {
  .layout-default__main {
    padding-bottom: 0;
  }
}

.layout-default__main:focus {
  outline: none;
}
</style>
