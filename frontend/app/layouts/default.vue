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
</script>

<template>
  <div class="layout-default">
    <SkipNav />
    <SiteHeader />
    <NeuralTicker :items="tickerItems" />

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

.layout-default__main {
  flex: 1;
  /* Offset for fixed header (4rem) + ticker (2rem) */
  padding-top: 6rem;
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
