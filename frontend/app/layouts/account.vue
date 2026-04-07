<script setup lang="ts">
import type { IconName } from '~/components/ui/icons'

const tickerItems = [
  { text: 'Now Showing: The Void Beyond, Neon Requiem, Midnight Parallax' },
  { text: 'Coming Soon: Glass Meridian' },
  { text: 'Premier members get early access to summer blockbusters' },
]

const accountNavItems: Array<{ label: string; href: string; icon: IconName }> = [
  { label: 'Dashboard', href: '/account', icon: 'home' },
  { label: 'Profile', href: '/account/profile', icon: 'account' },
  { label: 'Orders', href: '/account/orders', icon: 'orders' },
  { label: 'Loyalty', href: '/account/loyalty', icon: 'loyalty' },
  { label: 'Bookings', href: '/account/bookings', icon: 'bookings' },
  { label: 'Payment', href: '/account/payment-methods', icon: 'payment' },
]
</script>

<template>
  <div class="layout-account">
    <SkipNav />
    <SiteHeader />
    <NeuralTicker :items="tickerItems" />

    <div class="layout-account__body">
      <SidebarNav :items="accountNavItems" />

      <main id="main-content" tabindex="-1" class="layout-account__main">
        <slot />
      </main>
    </div>

    <SiteFooter />
    <CvToastContainer />
  </div>
</template>

<style scoped>
.layout-account {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

.layout-account__body {
  display: flex;
  flex: 1;
  max-width: 90rem;
  width: 100%;
  margin-inline: auto;
  padding-inline: var(--space-md);
  /* Offset for fixed header (4rem) + ticker (2rem) */
  padding-top: 6rem;
  gap: var(--space-2xl);
}

@media (min-width: 40rem) {
  .layout-account__body {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .layout-account__body {
    padding-inline: var(--space-2xl);
  }
}

.layout-account__main {
  flex: 1;
  min-width: 0;
  /* Offset for mobile bottom nav on small screens */
  padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px));
}

@media (min-width: 60rem) {
  .layout-account__main {
    padding-bottom: 0;
  }
}

.layout-account__main:focus {
  outline: none;
}
</style>
