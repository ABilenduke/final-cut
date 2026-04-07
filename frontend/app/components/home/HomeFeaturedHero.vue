<script setup lang="ts">
import type { Movie } from '~/types/movie'
import type { Showtime } from '~/types/showtime'

const props = defineProps<{
  movie: Movie
  nextShowtime: Showtime | null
  locationSelected: boolean
  loading: boolean
}>()

const emit = defineEmits<{
  'choose-location': []
  'track': [event: { action: string; movieSlug: string }]
}>()

const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' })

const formattedTime = computed(() => {
  if (!props.nextShowtime) return ''
  return timeFormatter.format(new Date(props.nextShowtime.startTime))
})

useHead({
  link: props.movie.backdropUrl
    ? [{ rel: 'preload', as: 'image', href: props.movie.backdropUrl }]
    : [],
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

      <!-- CTA area: depends on location state -->
      <div class="home-hero__cta">
        <!-- State 1: No location selected -->
        <CvButton
          v-if="!locationSelected"
          variant="secondary"
          @click="emit('choose-location'); emit('track', { action: 'choose-location', movieSlug: movie.slug })"
        >
          Choose a Location
        </CvButton>

        <!-- State 2: Loading showtimes -->
        <CvSkeletonLoader
          v-else-if="loading"
          variant="text"
          width="12rem"
          height="3rem"
        />

        <!-- State 3: Has showtime -->
        <template v-else-if="nextShowtime">
          <CvButton
            variant="primary"
            :href="`/purchase/${nextShowtime.id}`"
            :aria-label="`Get tickets for ${movie.title} at ${formattedTime}`"
            @click="emit('track', { action: 'hero-cta', movieSlug: movie.slug })"
          >
            Get Tickets &mdash; {{ formattedTime }}
          </CvButton>
          <NuxtLink
            :to="`/movies/${movie.slug}`"
            class="home-hero__more-link body-sm"
            @click="emit('track', { action: 'hero-more-showtimes', movieSlug: movie.slug })"
          >
            More showtimes
          </NuxtLink>
        </template>

        <!-- State 4: No showtimes available -->
        <CvButton
          v-else
          variant="secondary"
          :href="`/movies/${movie.slug}`"
          @click="emit('track', { action: 'hero-fallback', movieSlug: movie.slug })"
        >
          View Showtimes
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

.home-hero__more-link {
  color: var(--secondary);
  text-decoration: none;
}

.home-hero__more-link:hover {
  text-decoration: underline;
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
