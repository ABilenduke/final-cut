<script setup lang="ts">
import type { Movie } from '~/types/movie'

const props = withDefaults(defineProps<{
  movie: Movie
  variant?: 'now-showing' | 'coming-soon'
}>(), {
  variant: 'now-showing',
})

const emit = defineEmits<{
  notify: [slug: string]
  'track': [event: { action: string; movieSlug: string }]
}>()
</script>

<template>
  <CvCard class="movie-card">
    <template #header>
      <NuxtLink
        :to="`/movies/${movie.slug}`"
        class="movie-card__link"
        @click="emit('track', { action: 'title-click', movieSlug: movie.slug })"
      >
        <div class="movie-card__poster-container">
          <img
            v-if="movie.posterUrl"
            :src="movie.posterUrl"
            :alt="`${movie.title} poster`"
            class="movie-card__poster"
            loading="lazy"
          />
        </div>
        <h3 class="movie-card__title headline-sm">{{ movie.title }}</h3>
      </NuxtLink>
    </template>

    <template v-if="variant === 'now-showing'">
      <div class="movie-card__meta">
        <MovieRatingBadge :rating="movie.rating" />
        <div class="movie-card__genres">
          <CvBadge v-for="genre in movie.genres.slice(0, 3)" :key="genre.id" size="sm">
            {{ genre.name }}
          </CvBadge>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="movie-card__meta">
        <span class="movie-card__release-date label-lg">{{ formatDate(movie.releaseDate) }}</span>
        <div class="movie-card__genres">
          <CvBadge v-for="genre in movie.genres.slice(0, 3)" :key="genre.id" size="sm">
            {{ genre.name }}
          </CvBadge>
        </div>
      </div>
    </template>

    <template #footer>
      <CvButton
        v-if="variant === 'now-showing'"
        variant="tertiary"
        :href="`/movies/${movie.slug}`"
        @click="emit('track', { action: 'cta-click', movieSlug: movie.slug })"
      >
        View Showtimes
      </CvButton>
      <CvButton
        v-else
        variant="secondary"
        @click="emit('notify', movie.slug); emit('track', { action: 'notify-click', movieSlug: movie.slug })"
      >
        Notify Me
      </CvButton>
    </template>
  </CvCard>
</template>

<style scoped>
.movie-card__link {
  text-decoration: none;
  color: inherit;
  display: block;
}

.movie-card__poster-container {
  aspect-ratio: 2 / 3;
  background-color: var(--surface-container-lowest);
  border-radius: 0.125rem;
  overflow: hidden;
  margin-bottom: var(--space-sm);
}

.movie-card__poster {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
  display: block;
}

.movie-card__title {
  color: var(--on-surface);
  margin: 0;
}

.movie-card__meta {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.movie-card__genres {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
}

.movie-card__release-date {
  color: var(--secondary);
}
</style>
