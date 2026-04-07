<template>
  <div class="layout-blank">
    <div class="layout-blank__content">
      <NuxtLink to="/" class="layout-blank__logo">
        Final Cut
      </NuxtLink>

      <main id="main-content" tabindex="-1" class="layout-blank__main">
        <slot />
      </main>
    </div>

    <CvToastContainer />
  </div>
</template>

<style scoped>
.layout-blank {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100dvh;
  padding: var(--space-lg);
}

.layout-blank__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 25rem;
  gap: var(--space-2xl);
}

.layout-blank__logo {
  font-family: var(--font-display);
  font-size: var(--type-display-sm);
  line-height: 1.1;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  text-decoration: none;
}

.layout-blank__main {
  width: 100%;
}

.layout-blank__main:focus {
  outline: none;
}
</style>
