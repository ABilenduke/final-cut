<script setup lang="ts">
import type { Movie } from '~/types/movie'

defineProps<{
  movie: Movie
}>()
</script>

<template>
  <section class="wide-frame movie-hero">
    <!-- Backdrop image (decorative) -->
    <img
      v-if="movie.backdropUrl"
      :src="movie.backdropUrl"
      alt=""
      aria-hidden="true"
      class="movie-hero__backdrop"
    />

    <!-- Vignette bloom gradient overlay -->
    <div class="movie-hero__overlay vignette-bloom" aria-hidden="true" />

    <!-- Content -->
    <div class="wide-frame__content movie-hero__content">
      <h1 class="movie-hero__title display-lg">{{ movie.title }}</h1>
      <p v-if="movie.tagline" class="movie-hero__tagline body-lg">{{ movie.tagline }}</p>
    </div>
  </section>
</template>

<style scoped>
.movie-hero {
  position: relative;
  min-height: 24rem;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
  background-color: var(--surface-container-lowest);
}

.movie-hero__backdrop {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.movie-hero__overlay {
  position: absolute;
  inset: 0;
}

.movie-hero__content {
  position: relative;
  z-index: 1;
  padding-block: var(--space-3xl) var(--space-2xl);
}

.movie-hero__title {
  color: var(--on-surface);
  margin: 0;
}

.movie-hero__tagline {
  color: var(--tertiary);
  margin: var(--space-sm) 0 0;
}

/* Cinematic reveal animation */
.movie-hero__content {
  animation: hero-reveal var(--duration-cinematic) var(--ease-enter) both;
}

@keyframes hero-reveal {
  from {
    opacity: 0;
    transform: translateY(1rem);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Reduced motion: no animation */
@media (prefers-reduced-motion: reduce) {
  .movie-hero__content {
    animation: none;
  }
}
</style>
