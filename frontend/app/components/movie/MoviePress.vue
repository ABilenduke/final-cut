<script setup lang="ts">
// TODO(backend): replace with real press quotes & aggregate scores when a
// reviews model exists. Each quote is modeled as ordered segments so we
// can render italics (or other emphasis) without falling back to v-html.
interface QuoteSegment {
  text: string
  emphasis?: boolean
}
interface PressQuote {
  segments: readonly QuoteSegment[]
  author: string
  publication: string
}

const QUOTES: readonly PressQuote[] = [
  {
    segments: [
      { text: 'A trilogy closer of monastic restraint — Villeneuve has made his ' },
      { text: 'Andrei Rublev', emphasis: true },
      { text: ', shot on sand.' },
    ],
    author: 'Léa Ferrand',
    publication: 'Sight & Sound',
  },
  {
    segments: [
      { text: 'Florence Pugh walks in like weather. The third act is hers, and it is the best hour of film I have seen this year.' },
    ],
    author: 'Marcus Okafor',
    publication: 'The Guardian',
  },
  {
    segments: [
      { text: 'Projected on 70mm, the grain itself is a character. Do not watch this on a laptop. Do not watch this on a phone.' },
    ],
    author: 'Ana Ríos',
    publication: 'Film Comment',
  },
] as const

const SCORES = [
  { source: 'Final Cut Critic', n: 94, denom: '/100', bar: 94, accent: true },
  { source: 'Aggregate Press', n: 91, denom: '/100', bar: 91, accent: false },
  { source: 'Audience · Flagship', n: 4.7, denom: '/5', bar: 94, accent: false },
  { source: "Members' Rating", n: 4.9, denom: '/5', bar: 98, accent: false },
] as const
</script>

<template>
  <div>
    <div class="bay-eyebrow">Press &amp; Critics</div>
    <h2 class="bay-title">What they're saying.</h2>

    <div class="movie-press__reviews">
      <blockquote
        v-for="q in QUOTES"
        :key="q.author"
        class="movie-press__quote"
      >
        <p>
          <template v-for="(seg, i) in q.segments" :key="i">
            <em v-if="seg.emphasis">{{ seg.text }}</em>
            <template v-else>{{ seg.text }}</template>
          </template>
        </p>
        <footer class="movie-press__foot">
          <b>{{ q.author }}</b>
          <span>{{ q.publication }}</span>
        </footer>
      </blockquote>
    </div>

    <div class="movie-press__scores" aria-label="Critic and audience scores">
      <div
        v-for="s in SCORES"
        :key="s.source"
        class="movie-press__score-cell"
      >
        <span class="movie-press__score-src">{{ s.source }}</span>
        <span class="movie-press__score-n">
          <em v-if="s.accent">{{ s.n }}</em>
          <template v-else>{{ s.n }}</template>
          <span class="movie-press__score-denom">{{ s.denom }}</span>
        </span>
        <div class="movie-press__score-bar" :style="{ '--w': `${s.bar}%` }" aria-hidden="true" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.movie-press__reviews {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-lg);
  margin-top: var(--space-xl);
}

.movie-press__quote {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-lg);
  background: var(--surface-container);
  border-radius: var(--radius-card);
  min-height: 15rem;
  margin: 0;
}

.movie-press__quote::before {
  content: '“';
  font-family: var(--font-display);
  font-style: italic;
  font-size: 4rem;
  line-height: 0.7;
  color: var(--secondary);
  opacity: 0.65;
}

.movie-press__quote p {
  font-family: var(--font-display);
  font-size: 1.125rem;
  line-height: 1.4;
  color: var(--on-surface);
  text-wrap: pretty;
  flex: 1;
  margin: 0;
}

.movie-press__quote p :deep(em) {
  font-style: italic;
  color: var(--secondary);
}

.movie-press__foot {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-sm);
  border-top: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
  font-size: 0.6875rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-press__foot b {
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.2em;
}

/* ——— Scores row ——— */
.movie-press__scores {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0;
  margin-top: var(--space-xl);
  border-top: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
  border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
}

.movie-press__score-cell {
  padding: var(--space-lg) var(--space-md);
  border-right: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.15);
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.movie-press__score-cell:last-child {
  border-right: none;
}

.movie-press__score-src {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-press__score-n {
  font-family: var(--font-display);
  font-size: 2.5rem;
  line-height: 1;
  letter-spacing: -0.025em;
  color: var(--on-surface);
  font-weight: 500;
}

.movie-press__score-n em {
  color: var(--secondary);
  font-style: normal;
}

.movie-press__score-denom {
  font-size: 0.875rem;
  color: var(--on-tertiary-fixed-variant);
  margin-left: 0.2em;
}

.movie-press__score-bar {
  height: 0.125rem;
  background: rgba(var(--outline-variant-rgb), 0.3);
  margin-top: 0.3rem;
  position: relative;
  overflow: hidden;
}

.movie-press__score-bar::before {
  content: '';
  position: absolute;
  inset: 0;
  width: var(--w, 80%);
  background: var(--secondary);
}

@media (max-width: 68.75rem) {
  .movie-press__reviews {
    grid-template-columns: 1fr 1fr;
  }

  .movie-press__scores {
    grid-template-columns: 1fr 1fr;
  }

  .movie-press__score-cell:nth-child(2) {
    border-right: none;
  }

  .movie-press__score-cell:nth-child(1),
  .movie-press__score-cell:nth-child(2) {
    border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.15);
  }
}

@media (max-width: 39.999rem) {
  .movie-press__reviews {
    grid-template-columns: 1fr;
  }
}
</style>
