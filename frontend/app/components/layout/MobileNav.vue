<script setup lang="ts">
const route = useRoute()

const navItems = [
  { label: 'Home', href: '/', icon: 'home' as const },
  { label: 'Movies', href: '/movies', icon: 'movie' as const },
  { label: "What's On", href: '/whats-on', icon: 'calendar' as const },
  { label: 'Account', href: '/account', icon: 'account' as const },
  { label: 'More', href: '/contact', icon: 'more-horiz' as const },
]

function isActive(href: string): boolean {
  if (href === '/') return route.path === '/'
  return route.path.startsWith(href)
}
</script>

<template>
  <nav class="mobile-nav" aria-label="Mobile navigation">
    <NuxtLink
      v-for="item in navItems"
      :key="item.href"
      :to="item.href"
      class="mobile-nav__item"
      :class="{ 'mobile-nav__item--active': isActive(item.href) }"
      :aria-current="isActive(item.href) ? 'page' : undefined"
      :aria-label="item.label"
    >
      <CvIcon :name="item.icon" size="md" />
      <span class="mobile-nav__label">{{ item.label }}</span>
    </NuxtLink>
  </nav>
</template>

<style scoped>
.mobile-nav {
  display: flex;
  align-items: center;
  justify-content: space-around;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: calc(3.5rem + env(safe-area-inset-bottom, 0rem));
  padding-bottom: env(safe-area-inset-bottom, 0rem);
  background-color: var(--surface-container);
  z-index: var(--z-sticky);
}

@media (min-width: 60rem) {
  .mobile-nav {
    display: none;
  }
}

.mobile-nav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2xs);
  min-width: 3rem;
  min-height: 3rem;
  padding: var(--space-xs);
  color: var(--tertiary);
  text-decoration: none;
  transition: color var(--duration-micro) var(--ease-standard);
}

.mobile-nav__item--active {
  color: var(--secondary);
}

.mobile-nav__label {
  font-family: var(--font-body);
  font-size: var(--type-label-sm);
  line-height: 1;
}
</style>
