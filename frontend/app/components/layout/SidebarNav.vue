<script setup lang="ts">
import type { IconName } from '~/components/ui/icons'

interface SidebarNavItem {
  label: string
  href: string
  icon: IconName
}

defineProps<{
  items: SidebarNavItem[]
}>()

const route = useRoute()
const { logout } = useAuth()

function isActive(href: string): boolean {
  if (href === '/account') return route.path === '/account'
  return route.path.startsWith(href)
}

async function handleLogout(): Promise<void> {
  await logout()
  await navigateTo('/')
}
</script>

<template>
  <!-- Desktop / Tablet sidebar -->
  <nav class="sidebar-nav" aria-label="Account">
    <NuxtLink
      v-for="item in items"
      :key="item.href"
      :to="item.href"
      class="sidebar-nav__item"
      :class="{ 'sidebar-nav__item--active': isActive(item.href) }"
      :aria-current="isActive(item.href) ? 'page' : undefined"
    >
      <CvIcon :name="item.icon" size="md" />
      <span class="sidebar-nav__label">{{ item.label }}</span>
    </NuxtLink>
    <button
      type="button"
      class="sidebar-nav__item sidebar-nav__logout"
      @click="handleLogout"
    >
      <CvIcon name="logout" size="md" />
      <span class="sidebar-nav__label">Log Out</span>
    </button>
  </nav>

  <!-- Mobile bottom bar (below screen-md) -->
  <nav class="sidebar-nav-mobile" aria-label="Account">
    <NuxtLink
      v-for="item in items"
      :key="item.href"
      :to="item.href"
      class="sidebar-nav-mobile__item"
      :class="{ 'sidebar-nav-mobile__item--active': isActive(item.href) }"
      :aria-current="isActive(item.href) ? 'page' : undefined"
      :aria-label="item.label"
    >
      <CvIcon :name="item.icon" size="md" />
      <span class="sidebar-nav-mobile__label">{{ item.label }}</span>
    </NuxtLink>
  </nav>
</template>

<style scoped>
/* Desktop sidebar: 15rem with labels */
.sidebar-nav {
  display: none;
  flex-direction: column;
  gap: var(--space-xs);
  width: 15rem;
  flex-shrink: 0;
  padding: var(--space-md) 0;
}

@media (min-width: 60rem) {
  .sidebar-nav {
    display: flex;
  }
}

.sidebar-nav__item {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  color: var(--tertiary);
  text-decoration: none;
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  border-radius: 0.125rem; /* token-exception: design system radius */
  position: relative;
  transition: color var(--duration-micro) var(--ease-standard);
}

.sidebar-nav__item:hover {
  color: var(--on-surface);
}

.sidebar-nav__item--active {
  color: var(--secondary);
}

/* Logout is a button, not a link — reset native chrome so it shares
 * the link treatment and sits at the bottom of the rail. */
.sidebar-nav__logout {
  background: none;
  border: 0;
  cursor: pointer;
  font: inherit;
  text-align: left;
  width: 100%;
  margin-top: auto;
}

.sidebar-nav__item--active::before {
  content: '';
  position: absolute;
  left: 0;
  top: var(--space-xs);
  bottom: var(--space-xs);
  width: 0.1875rem; /* token-exception: accent border width */
  background: linear-gradient(
    180deg,
    var(--secondary) 0%,
    var(--primary-container) 100%
  );
  border-radius: 0.125rem; /* token-exception: design system radius */
}

/* Tablet: 4rem icon-only rail */
@media (min-width: 60rem) and (max-width: 79.999rem) {
  .sidebar-nav {
    width: 4rem;
    align-items: center;
  }

  .sidebar-nav__item {
    justify-content: center;
    padding: var(--space-sm);
  }

  .sidebar-nav__label {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }

  .sidebar-nav__item--active::before {
    top: 0;
    bottom: 0;
  }
}

/* Mobile: bottom bar */
.sidebar-nav-mobile {
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
  .sidebar-nav-mobile {
    display: none;
  }
}

.sidebar-nav-mobile__item {
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

.sidebar-nav-mobile__item--active {
  color: var(--secondary);
}

.sidebar-nav-mobile__label {
  font-family: var(--font-body);
  font-size: var(--type-label-sm);
  line-height: 1;
}
</style>
