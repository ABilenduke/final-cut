<script setup lang="ts">
import type { Movie } from '~/types/movie'

const props = defineProps<{
  movie: Movie
}>()

// Split synopsis into a lead sentence + remaining body paragraphs for editorial flow.
const synopsisParts = computed(() => {
  const text = (props.movie.synopsis || '').trim()
  if (!text) return { lead: '', body: [] as string[] }
  const firstStop = text.search(/[.!?]\s+/)
  if (firstStop < 0) return { lead: text, body: [] }
  const lead = text.slice(0, firstStop + 1).trim()
  const remainder = text.slice(firstStop + 1).trim()
  if (!remainder) return { lead, body: [] }
  const paragraphs = remainder.split(/\n\s*\n/).map(p => p.trim()).filter(Boolean)
  return { lead, body: paragraphs.length > 0 ? paragraphs : [remainder] }
})

// TODO(backend): replace with real crew fields when the Movie schema adds them.
// Until then, render neutral placeholders instead of misleading hard-coded credits.
const STUB_CREDITS = {
  director: '—',
  screenplay: '—',
  cinematography: '—',
  editor: '—',
  composer: '—',
  aspect: '—',
  advisory: '—',
}

const genreLabel = computed(() =>
  props.movie.genres.length > 0
    ? props.movie.genres.map(g => g.name).join(', ')
    : '—',
)
</script>

<template>
  <div class="movie-detail">
    <div class="movie-detail__body">
      <div class="bay-eyebrow">Synopsis · Reel 01</div>
      <p v-if="synopsisParts.lead" class="movie-detail__lead">{{ synopsisParts.lead }}</p>
      <p
        v-for="(paragraph, i) in synopsisParts.body"
        :key="i"
        class="movie-detail__para"
      >
        {{ paragraph }}
      </p>
    </div>
    <div class="movie-detail__facts">
      <div class="bay-eyebrow">Credits · Programme Notes</div>
      <div class="movie-detail__rows">
        <div class="movie-detail__row">
          <span class="movie-detail__k">Director</span>
          <span class="movie-detail__v">{{ STUB_CREDITS.director }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Screenplay</span>
          <span class="movie-detail__v">{{ STUB_CREDITS.screenplay }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Cinematography</span>
          <span class="movie-detail__v">{{ STUB_CREDITS.cinematography }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Editor</span>
          <span class="movie-detail__v">{{ STUB_CREDITS.editor }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Composer</span>
          <span class="movie-detail__v">{{ STUB_CREDITS.composer }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Runtime</span>
          <span class="movie-detail__v">{{ formatRuntime(movie.runtime) }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Genres</span>
          <span class="movie-detail__v">{{ genreLabel }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Aspect</span>
          <span class="movie-detail__v">{{ STUB_CREDITS.aspect }}</span>
        </div>
        <div class="movie-detail__row">
          <span class="movie-detail__k">Advisory</span>
          <span class="movie-detail__v">{{ STUB_CREDITS.advisory }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.movie-detail {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: var(--space-3xl);
  padding-top: var(--space-2xl);
}

.movie-detail__body {
  max-width: 62ch;
}

.movie-detail__lead {
  font-family: var(--font-display);
  font-size: 1.625rem;
  line-height: 1.35;
  letter-spacing: -0.01em;
  color: var(--on-surface);
  margin: 0 0 var(--space-lg);
  text-wrap: pretty;
}

.movie-detail__para {
  font-size: 1.0625rem;
  color: var(--tertiary);
  line-height: 1.65;
  margin: 0 0 var(--space-md);
  text-wrap: pretty;
}

.movie-detail__para:last-child {
  margin-bottom: 0;
}

.movie-detail__rows {
  display: flex;
  flex-direction: column;
  border-top: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.3); /* token-exception: sub-pixel rule */
}

.movie-detail__row {
  display: grid;
  grid-template-columns: 1fr 1.6fr;
  gap: var(--space-md);
  padding: var(--space-md) 0;
  border-bottom: 0.0625rem solid rgba(var(--outline-variant-rgb), 0.2); /* token-exception: sub-pixel rule */
}

.movie-detail__k {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding-top: 0.2rem;
}

.movie-detail__v {
  color: var(--on-surface);
  font-size: 0.9375rem;
  line-height: 1.45;
}

@media (max-width: 68.75rem) {
  .movie-detail {
    grid-template-columns: 1fr;
    gap: var(--space-xl);
  }
}
</style>
