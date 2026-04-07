<script setup lang="ts">
const tickerItems = [
  { text: 'Now Showing: The Void Beyond, Neon Requiem, Midnight Parallax' },
  { text: 'Coming Soon: Glass Meridian' },
  { text: 'Premier members get early access to summer blockbusters' },
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
  padding-bottom: calc(3.5rem + env(safe-area-inset-bottom, 0px));
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
