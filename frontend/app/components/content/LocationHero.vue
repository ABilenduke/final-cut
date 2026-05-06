<script setup lang="ts">
import type { Location } from '~/types/location'

defineProps<{
  location: Location
}>()
</script>

<template>
  <section class="location-hero">
    <!-- Backdrop image or gradient fallback -->
    <div class="location-hero__bg" aria-hidden="true" />

    <img
      v-if="location.imageUrl"
      :src="location.imageUrl"
      alt=""
      aria-hidden="true"
      class="location-hero__image"
      loading="eager"
    />

    <div class="location-hero__inner">
      <p class="location-hero__eyebrow" aria-hidden="true">Final Cut · Cinema</p>
      <h1 class="location-hero__name">{{ location.name }}</h1>
      <p class="location-hero__tagline">{{ location.city }}, {{ location.state }}</p>
    </div>
  </section>
</template>

<style scoped>
.location-hero {
  position: relative;
  min-height: 24rem;
  overflow: hidden;
  display: flex;
  align-items: flex-end;
}

/* Vignette bloom gradient — primary_container to surface_container_lowest */
.location-hero__bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    radial-gradient(
      ellipse 70% 80% at 30% 60%,
      rgba(var(--primary-container-rgb), 0.5),
      transparent 65%
    ),
    linear-gradient(
      180deg,
      var(--surface-container) 0%,
      var(--surface-container-lowest) 100%
    );
}

.location-hero__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  z-index: 1;
  opacity: 0.3;
  mix-blend-mode: luminosity;
}

.location-hero__inner {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 90rem;
  margin-inline: auto;
  padding: var(--space-2xl) var(--space-md) var(--space-2xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  animation: location-hero-reveal var(--duration-cinematic) var(--ease-enter) both;
}

@keyframes location-hero-reveal {
  from { opacity: 0; transform: translateY(1rem); }
  to   { opacity: 1; transform: translateY(0); }
}

.location-hero__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin: 0;
}

.location-hero__name {
  font-family: var(--font-display);
  font-size: clamp(2.25rem, 5vw, 4rem);
  font-weight: 500;
  letter-spacing: -0.03em;
  line-height: 0.95;
  color: var(--on-surface);
  margin: 0;
}

.location-hero__tagline {
  font-family: var(--font-body);
  font-size: var(--type-body-lg, 1.125rem);
  color: var(--tertiary);
  margin: 0;
  font-style: italic;
}

@media (min-width: 40rem) {
  .location-hero__inner {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .location-hero__inner {
    padding-inline: var(--space-2xl);
  }

  .location-hero {
    min-height: 28rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .location-hero__inner {
    animation: none;
  }
}
</style>
