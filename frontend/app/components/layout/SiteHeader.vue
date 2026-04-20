<script setup lang="ts">
const route = useRoute()
const { user, isAuthenticated } = useAuth()
const { locations, activeLocation, setLocation } = useLocations()
const { activate, deactivate } = useFocusTrap()

const mobileMenuOpen = ref(false)
const mobileMenuEl = ref<HTMLElement | null>(null)

const navItems = [
  { label: 'Movies', href: '/movies' },
  { label: "What's On", href: '/whats-on' },
  { label: 'Food & Drink', href: '/food-drink' },
  { label: 'Events', href: '/events' },
  { label: 'Gift Cards', href: '/gift-cards' },
]

function isActive(href: string): boolean {
  if (href === '/') return route.path === '/'
  return route.path.startsWith(href)
}

function toggleMobileMenu() {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

function closeMobileMenu() {
  mobileMenuOpen.value = false
}

function onLocationChange(event: Event) {
  const target = event.target as HTMLSelectElement
  setLocation(target.value)
}

// Focus trap for mobile menu
watch(mobileMenuOpen, (open) => {
  if (open) {
    nextTick(() => {
      if (!mobileMenuOpen.value || !mobileMenuEl.value) {
        return
      }

      activate(mobileMenuEl.value)
    })
  } else {
    deactivate()
  }
})

onUnmounted(() => {
  deactivate()
})

// Close mobile menu on route change
watch(() => route.path, () => {
  closeMobileMenu()
})

// Close mobile menu on Escape
function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && mobileMenuOpen.value) {
    closeMobileMenu()
  }
}
</script>

<template>
  <header class="site-header" role="banner">
    <div class="site-header__inner">
      <NuxtLink to="/" class="site-header__wordmark" aria-label="Final Cut — home">
        <span class="site-header__mark" aria-hidden="true">◉</span>
        <span class="site-header__wordmark-name">Final Cut</span>
        <span class="site-header__wordmark-est" aria-hidden="true">est. 2003</span>
      </NuxtLink>

      <!-- Desktop Nav -->
      <nav class="site-header__nav" aria-label="Primary">
        <NuxtLink
          v-for="item in navItems"
          :key="item.href"
          :to="item.href"
          class="site-header__nav-link"
          :class="{ 'site-header__nav-link--active': isActive(item.href) }"
        >
          {{ item.label }}
        </NuxtLink>
      </nav>

      <!-- Right cluster: location pill + auth -->
      <div class="site-header__right">
        <div v-if="locations.length > 0" class="site-header__loc-pill">
          <CvIcon name="location" size="sm" aria-hidden="true" />
          <label for="location-select" class="sr-only">Theater location</label>
          <select
            id="location-select"
            class="site-header__loc-select"
            :value="activeLocation?.slug ?? ''"
            @change="onLocationChange"
          >
            <option v-for="loc in locations" :key="loc.slug" :value="loc.slug">
              {{ loc.name }}
            </option>
          </select>
        </div>

        <div class="site-header__auth">
          <template v-if="isAuthenticated">
            <NuxtLink to="/account" class="site-header__avatar" :aria-label="`Account for ${user?.name}`">
              <CvIcon name="account" size="md" />
            </NuxtLink>
          </template>
          <template v-else>
            <NuxtLink to="/auth/login" class="site-header__sign-in">
              Sign In
            </NuxtLink>
          </template>
        </div>
      </div>

      <!-- Mobile Menu Toggle -->
      <button
        type="button"
        class="site-header__hamburger"
        :aria-expanded="mobileMenuOpen"
        aria-controls="mobile-menu"
        aria-label="Menu"
        @click="toggleMobileMenu"
      >
        <CvIcon :name="mobileMenuOpen ? 'close' : 'menu'" size="md" />
      </button>
    </div>

  </header>

  <!-- Mobile Menu Overlay (teleported to body for proper focus trap isolation) -->
  <ClientOnly>
    <Teleport to="body">
      <Transition name="site-header-menu">
        <div
          v-if="mobileMenuOpen"
          id="mobile-menu"
          ref="mobileMenuEl"
          class="site-header__mobile-menu"
          @keydown="onKeydown"
        >
          <nav aria-label="Primary">
            <NuxtLink
              v-for="item in navItems"
              :key="item.href"
              :to="item.href"
              class="site-header__mobile-link"
              :class="{ 'site-header__mobile-link--active': isActive(item.href) }"
              @click="closeMobileMenu"
            >
              {{ item.label }}
            </NuxtLink>
            <div class="site-header__mobile-divider" />
            <template v-if="isAuthenticated">
              <NuxtLink
                to="/account"
                class="site-header__mobile-link"
                @click="closeMobileMenu"
              >
                My Account
              </NuxtLink>
            </template>
            <template v-else>
              <NuxtLink
                to="/auth/login"
                class="site-header__mobile-link"
                @click="closeMobileMenu"
              >
                Sign In
              </NuxtLink>
            </template>
          </nav>
        </div>
      </Transition>
    </Teleport>
    <template #fallback />
  </ClientOnly>
</template>

<style scoped>
.site-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 4rem;
  background-color: rgb(14 14 14 / 0.72);
  -webkit-backdrop-filter: blur(1.25rem);
  backdrop-filter: blur(1.25rem);
  border-bottom: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2); /* token-exception: sub-pixel edge */
  z-index: var(--z-sticky);
}

.site-header__inner {
  display: flex;
  align-items: center;
  height: 100%;
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-md);
  gap: var(--space-xl);
}

@media (min-width: 40rem) {
  .site-header__inner {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .site-header__inner {
    padding-inline: var(--space-2xl);
  }
}

/* Wordmark */
.site-header__wordmark {
  display: inline-flex;
  align-items: baseline;
  gap: 0.4em;
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 1;
  color: var(--on-surface);
  text-decoration: none;
  flex-shrink: 0;
}

.site-header__mark {
  color: var(--primary-container);
  font-size: 0.9em;
}

.site-header__wordmark-name {
  color: var(--on-surface);
}

.site-header__wordmark-est {
  font-style: italic;
  color: var(--tertiary);
  font-weight: 400;
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

/* Desktop Nav */
.site-header__nav {
  display: none;
  align-items: center;
  gap: var(--space-lg);
  flex: 1;
  margin-left: var(--space-md);
}

@media (min-width: 60rem) {
  .site-header__nav {
    display: flex;
  }
}

.site-header__nav-link {
  font-family: var(--font-body);
  font-size: 0.9375rem;
  line-height: 1;
  color: var(--on-surface);
  text-decoration: none;
  position: relative;
  padding-block: var(--space-xs);
  transition: color var(--duration-standard) var(--ease-standard);
}

.site-header__nav-link::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -0.125rem; /* token-exception: sub-pixel decorative underline */
  height: 0.0625rem;
  background-color: var(--secondary);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform var(--duration-standard) var(--ease-standard);
}

.site-header__nav-link:hover {
  color: var(--secondary);
}

.site-header__nav-link:hover::after,
.site-header__nav-link--active::after {
  transform: scaleX(1);
}

.site-header__nav-link--active {
  color: var(--secondary);
}

/* Right cluster */
.site-header__right {
  display: none;
  align-items: center;
  gap: var(--space-md);
  margin-left: auto;
}

@media (min-width: 60rem) {
  .site-header__right {
    display: flex;
  }
}

/* Location pill */
.site-header__loc-pill {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-xs) var(--space-sm);
  background-color: rgb(42 42 42 / 0.5);
  border-radius: 0.125rem; /* token-exception: component-specific radius */
  color: var(--tertiary);
  font-size: 0.8125rem;
}

.site-header__loc-select {
  background: none;
  border: none;
  color: var(--on-surface);
  font-family: var(--font-body);
  font-size: 0.8125rem;
  font-weight: 500;
  padding: 0;
  cursor: pointer;
  max-width: 12rem;
}

.site-header__loc-select:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
  border-radius: 0.125rem;
}

/* Auth */
.site-header__auth {
  display: flex;
  align-items: center;
}

.site-header__sign-in {
  font-family: var(--font-body);
  font-size: 0.9375rem;
  color: var(--secondary);
  text-decoration: none;
  padding: var(--space-sm) var(--space-sm);
}

.site-header__sign-in:hover {
  text-decoration: underline;
}

.site-header__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  color: var(--tertiary);
  text-decoration: none;
  border-radius: 50%; /* token-exception: avatar */
}

.site-header__avatar:hover {
  color: var(--on-surface);
}

/* Hamburger */
.site-header__hamburger {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  background: none;
  border: none;
  color: var(--on-surface);
  cursor: pointer;
  margin-left: auto;
}

@media (min-width: 60rem) {
  .site-header__hamburger {
    display: none;
  }
}

</style>

<!-- Unscoped styles for teleported mobile menu -->
<style>
.site-header__mobile-menu {
  position: fixed;
  top: 4rem;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--surface-container);
  z-index: var(--z-sticky);
  padding: var(--space-lg) var(--space-md);
  overflow-y: auto;
}

@media (min-width: 60rem) {
  .site-header__mobile-menu {
    display: none;
  }
}

.site-header__mobile-link {
  display: block;
  padding: var(--space-md);
  font-family: var(--font-body);
  font-size: var(--type-body-lg);
  color: var(--on-surface);
  text-decoration: none;
  min-height: 3rem;
}

.site-header__mobile-link:hover {
  color: var(--secondary);
}

.site-header__mobile-link--active {
  color: var(--secondary);
}

.site-header__mobile-divider {
  height: 0.0625rem; /* token-exception: sub-pixel decorative divider */
  background-color: rgb(var(--outline-variant-rgb) / 0.15);
  margin: var(--space-sm) var(--space-md);
}

/* Mobile menu transitions */
.site-header-menu-enter-active {
  transition: opacity var(--duration-standard) var(--ease-enter);
}

.site-header-menu-leave-active {
  transition: opacity var(--duration-standard) var(--ease-exit);
}

.site-header-menu-enter-from,
.site-header-menu-leave-to {
  opacity: 0;
}
</style>
