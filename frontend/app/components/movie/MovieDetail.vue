<script setup lang="ts">
import type { Movie } from '~/types/movie'

defineProps<{
  movie: Movie
}>()
</script>

<template>
  <div class="movie-detail">
    <h2 class="movie-detail__title headline-lg">{{ movie.title }}</h2>
    <p v-if="movie.tagline" class="movie-detail__tagline body-lg">{{ movie.tagline }}</p>

    <div class="movie-detail__meta">
      <MovieRatingBadge :rating="movie.rating" />
      <span class="movie-detail__runtime label-lg">{{ formatRuntime(movie.runtime) }}</span>
    </div>

    <div v-if="movie.genres.length > 0" class="movie-detail__genres">
      <CvBadge v-for="genre in movie.genres" :key="genre.id">{{ genre.name }}</CvBadge>
    </div>

    <p class="movie-detail__synopsis body-md">{{ movie.synopsis }}</p>

    <MovieTrailerEmbed
      v-if="movie.trailerKey"
      :trailer-key="movie.trailerKey"
      :title="movie.title"
    />

    <MovieCastList :cast="movie.cast" />
  </div>
</template>

<style scoped>
.movie-detail__title {
  color: var(--on-surface);
  margin: 0;
}

.movie-detail__tagline {
  color: var(--tertiary);
  margin: var(--space-xs) 0 0;
  font-style: italic;
}

.movie-detail__meta {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  margin-top: var(--space-md);
}

.movie-detail__runtime {
  color: var(--tertiary);
}

.movie-detail__genres {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
  margin-top: var(--space-sm);
}

.movie-detail__synopsis {
  color: var(--on-surface);
  margin: var(--space-lg) 0 0;
  line-height: 1.6;
}

/* Space before trailer and cast */
.movie-detail > :deep(.trailer-embed) {
  margin-top: var(--space-xl);
}

.movie-detail > :deep(.cast-list) {
  margin-top: var(--space-xl);
}
</style>
