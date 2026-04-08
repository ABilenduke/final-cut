<script setup lang="ts">
import type { Movie } from '~/types/movie'

defineProps<{
  movie: Movie
}>()

useHead({
  // Preload is set dynamically — only when backdropUrl exists
})
</script>

<template>
  <section class="wide-frame home-hero">
    <!-- Backdrop image (LCP target) -->
    <img
      v-if="movie.backdropUrl"
      :src="movie.backdropUrl"
      alt=""
      aria-hidden="true"
      class="home-hero__backdrop"
      loading="eager"
      fetchpriority="high"
    />

    <!-- Vignette bloom gradient overlay -->
    <div class="home-hero__overlay vignette-bloom" aria-hidden="true" />

    <!-- Content -->
    <div class="wide-frame__content home-hero__content">
      <h1 class="home-hero__title display-lg">{{ movie.title }}</h1>
      <p v-if="movie.tagline" class="home-hero__tagline body-lg">{{ movie.tagline }}</p>

      <div class="home-hero__cta">
        <CvButton
          variant="primary"
          :href="`/movies/${movie.slug}`"
          :aria-label="`Get tickets for ${movie.title}`"
        >
          Get Tickets
        </CvButton>
      </div>
    </div>
  </section>
</template>

<style scoped>
.home-hero {
  position: relative;
  min-height: 28rem;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
  background-color: var(--surface-container-lowest);
}

.home-hero__backdrop {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.home-hero__overlay {
  position: absolute;
  inset: 0;
}

.home-hero__content {
  position: relative;
  z-index: 1;
  padding-block: var(--space-3xl) var(--space-2xl);
  animation: home-hero-reveal var(--duration-cinematic) var(--ease-enter) both;
}

.home-hero__title {
  color: var(--on-surface);
  margin: 0;
}

.home-hero__tagline {
  color: var(--tertiary);
  margin: var(--space-sm) 0 0;
}

.home-hero__cta {
  margin-top: var(--space-lg);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-sm);
}

@keyframes home-hero-reveal {
  from {
    opacity: 0;
    transform: translateY(1rem);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .home-hero__content {
    animation: none;
  }
}
</style>
