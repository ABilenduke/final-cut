<script setup lang="ts">
</script>

<template>
  <div class="layout-purchase">
    <SkipNav />
    <header class="layout-purchase__header" role="banner">
      <div class="layout-purchase__header-inner">
        <NuxtLink to="/" class="layout-purchase__logo">
          Final Cut
        </NuxtLink>

        <!-- PurchaseStepIndicator is rendered by page components
             which control currentStep, completedSteps, and navigableSteps -->
        <div class="layout-purchase__steps">
          <slot name="step-indicator" />
        </div>

        <!-- Session timer placeholder (wired by purchase pages) -->
        <div class="layout-purchase__timer">
          <slot name="timer" />
        </div>
      </div>
    </header>

    <div class="layout-purchase__body">
      <main id="main-content" tabindex="-1" class="layout-purchase__main">
        <slot />
      </main>

      <!-- CartSummary sidebar placeholder (built in Plan 08) -->
      <aside v-if="$slots.cart" class="layout-purchase__cart" aria-label="Order summary">
        <slot name="cart" />
      </aside>
    </div>

    <CvToastContainer />
  </div>
</template>

<style scoped>
.layout-purchase {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

/* Header */
.layout-purchase__header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 4rem;
  background-color: var(--surface-container);
  z-index: var(--z-sticky);
}

.layout-purchase__header-inner {
  display: flex;
  align-items: center;
  height: 100%;
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-md);
  gap: var(--space-lg);
}

@media (min-width: 40rem) {
  .layout-purchase__header-inner {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .layout-purchase__header-inner {
    padding-inline: var(--space-2xl);
  }
}

.layout-purchase__logo {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  text-decoration: none;
  flex-shrink: 0;
}

.layout-purchase__steps {
  flex: 1;
  display: flex;
  justify-content: center;
}

.layout-purchase__timer {
  flex-shrink: 0;
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
}

/* Body */
.layout-purchase__body {
  display: flex;
  flex: 1;
  max-width: 90rem;
  width: 100%;
  margin-inline: auto;
  padding-inline: var(--space-md);
  padding-top: 4rem;
  gap: var(--space-2xl);
}

@media (min-width: 40rem) {
  .layout-purchase__body {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .layout-purchase__body {
    padding-inline: var(--space-2xl);
  }
}

.layout-purchase__main {
  flex: 1;
  min-width: 0;
  padding-top: var(--space-lg);
  padding-bottom: var(--space-xl);
}

.layout-purchase__main:focus {
  outline: none;
}

/* Cart sidebar (desktop) */
.layout-purchase__cart {
  display: none;
  width: 20rem;
  flex-shrink: 0;
  padding-top: var(--space-lg);
}

@media (min-width: 60rem) {
  .layout-purchase__cart {
    display: block;
  }
}
</style>
