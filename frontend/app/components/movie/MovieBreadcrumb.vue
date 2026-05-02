<script setup lang="ts">
defineProps<{
  title: string
}>()

function handleShare() {
  if (import.meta.client && navigator.share) {
    navigator.share({ url: window.location.href }).catch(() => {})
  }
}

function handlePrint() {
  if (import.meta.client) window.print()
}
</script>

<template>
  <nav class="movie-breadcrumb" aria-label="Breadcrumb">
    <NuxtLink to="/" class="movie-breadcrumb__link">Home</NuxtLink>
    <span class="movie-breadcrumb__sep" aria-hidden="true">/</span>
    <NuxtLink to="/movies" class="movie-breadcrumb__link">Now Showing</NuxtLink>
    <span class="movie-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="movie-breadcrumb__current" aria-current="page">{{ title }}</span>

    <div class="movie-breadcrumb__right">
      <button type="button" class="movie-breadcrumb__action" @click="handleShare">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z" />
        </svg>
        <span>Share</span>
      </button>
      <button type="button" class="movie-breadcrumb__action" @click="handlePrint">
        <span>Print Showtimes</span>
      </button>
    </div>
  </nav>
</template>

<style scoped>
.movie-breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  height: 2rem;
  padding-inline: var(--space-2xl);
  background: var(--surface-container-lowest);
  border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.15);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-breadcrumb__link {
  color: var(--on-tertiary-fixed-variant);
  text-decoration: none;
  transition: color var(--duration-standard) var(--ease-standard);
}

.movie-breadcrumb__link:hover {
  color: var(--secondary);
}

.movie-breadcrumb__link:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.movie-breadcrumb__sep {
  opacity: 0.4;
}

.movie-breadcrumb__current {
  color: var(--on-surface);
  font-weight: 500;
}

.movie-breadcrumb__right {
  margin-left: auto;
  display: flex;
  gap: var(--space-lg);
  align-items: center;
}

.movie-breadcrumb__action {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: none;
  border: none;
  color: var(--on-tertiary-fixed-variant);
  font: inherit;
  font-size: inherit;
  letter-spacing: inherit;
  text-transform: inherit;
  cursor: pointer;
  padding: 0;
  transition: color var(--duration-standard) var(--ease-standard);
}

.movie-breadcrumb__action:hover {
  color: var(--secondary);
}

.movie-breadcrumb__action:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

@media (max-width: 39.999rem) {
  .movie-breadcrumb {
    padding-inline: var(--space-md);
    font-size: 0.625rem;
  }

  .movie-breadcrumb__right {
    display: none;
  }
}
</style>
