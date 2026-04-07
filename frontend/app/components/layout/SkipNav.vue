<script setup lang="ts">
</script>

<template>
  <a href="#main-content" class="skip-nav">
    Skip to main content
  </a>
</template>

<style scoped>
.skip-nav {
  position: fixed;
  top: 0;
  left: var(--space-md);
  z-index: var(--z-skip-nav);
  padding: var(--space-sm) var(--space-md);
  background-color: var(--primary-container);
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  text-decoration: none;
  border-radius: 0.125rem; /* token-exception: design system radius */
  transform: translateY(-100%);
  transition: transform var(--duration-standard) var(--ease-enter);
}

.skip-nav:focus-visible {
  transform: translateY(0);
  outline: none;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}
</style>
