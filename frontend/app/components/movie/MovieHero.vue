<script setup lang="ts">
import type { Movie } from '~/types/movie'

const props = defineProps<{
  movie: Movie
}>()

// TODO(backend): replace with real crew fields when Movie schema adds them
const STUB_CREW = {
  director: 'Denis Villeneuve',
  writer: 'Jon Spaihts',
  cinematographer: 'Greig Fraser',
  composer: 'Hans Zimmer',
  studio: 'Warner · Legendary',
  grainLabel: 'Feature No. 01',
  grainReel: 'Reel 047 · 70mm source · Warner',
  format: '70mm · Atmos',
}

const clock = useClock()

const posterTitle = computed(() => props.movie.title)
const posterGlyph = computed(() => props.movie.title.charAt(0).toUpperCase() || '·')

// Split the title so we can color the last segment in gold italic.
// "Dune: Part Three" → lead="Dune", separator=":", accent="Part Three"
// "The Archive" → lead="The", separator="", accent="Archive"
const titleParts = computed(() => {
  const title = props.movie.title.trim()
  const colonMatch = title.match(/^(.+?)\s*:\s*(.+)$/)
  if (colonMatch) {
    return { lead: colonMatch[1], separator: ':', accent: colonMatch[2] }
  }
  const words = title.split(' ')
  if (words.length >= 2) {
    return {
      lead: words.slice(0, -1).join(' '),
      separator: '',
      accent: words[words.length - 1],
    }
  }
  return { lead: title, separator: '', accent: '' }
})

const releaseYear = computed(() => {
  if (!props.movie.releaseDate) return null
  return new Date(`${props.movie.releaseDate}T12:00:00Z`).getFullYear()
})

const ratingScore = computed(() =>
  props.movie.rating != null ? Math.round(props.movie.rating * 10) : null,
)

const statusLabel = computed(() =>
  props.movie.status === 'now_showing' ? 'Now Showing' : 'Coming Soon',
)

function scrollTo(id: string) {
  if (!import.meta.client) return
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function handleShare() {
  if (import.meta.client && navigator.share) {
    navigator.share({ url: window.location.href, title: props.movie.title }).catch(() => {})
  }
}
</script>

<template>
  <section class="movie-hero">
    <div class="movie-hero__bg" aria-hidden="true" />
    <div class="movie-hero__backdrop-art" aria-hidden="true" />

    <img
      v-if="movie.backdropUrl"
      :src="movie.backdropUrl"
      alt=""
      aria-hidden="true"
      class="movie-hero__backdrop-img"
      loading="eager"
    />

    <div class="movie-hero__grain-label">
      <b>{{ STUB_CREW.grainLabel }}</b>
      <span class="movie-hero__rule" aria-hidden="true" />
      <span>{{ STUB_CREW.grainReel }}</span>
    </div>

    <div class="movie-hero__telemetry" aria-hidden="true">
      <div class="movie-hero__clock">{{ clock }}</div>
      <div>Aud 01 · 217/220 seats</div>
      <div>Doors 19:00 · Showtime 19:30</div>
    </div>

    <div class="movie-hero__inner">
      <!-- Poster column -->
      <div class="movie-hero__poster-wrap">
        <div class="movie-hero__poster">
          <img
            v-if="movie.posterUrl"
            :src="movie.posterUrl"
            :alt="`${movie.title} poster`"
            class="movie-hero__poster-img"
          />
          <span v-else class="movie-hero__poster-glyph" aria-hidden="true">
            {{ posterGlyph }}
          </span>
          <span class="movie-hero__poster-badge">{{ STUB_CREW.format }}</span>
          <div class="movie-hero__poster-foot">
            <span class="movie-hero__poster-foot-s">Final Cut · Feature Programme</span>
            <span class="movie-hero__poster-foot-t">{{ posterTitle }}</span>
          </div>
        </div>
        <div class="movie-hero__poster-meta">
          <span>
            Released
            <b>{{ movie.releaseDate ? formatDate(movie.releaseDate) : '—' }}</b>
          </span>
          <span>
            Screen
            <b>01</b>
          </span>
          <span>
            Runtime
            <b>{{ formatRuntime(movie.runtime) }}</b>
          </span>
        </div>
      </div>

      <!-- Title column -->
      <div class="movie-hero__title-col">
        <div class="movie-hero__t-meta">
          <span class="movie-hero__t-dot" aria-hidden="true" />
          <b>{{ statusLabel }}</b>
          <span class="movie-hero__rule" aria-hidden="true" />
          <span>A Film at Final Cut</span>
        </div>

        <h1 class="movie-hero__title">
          {{ titleParts.lead }}<template v-if="titleParts.accent"><br><span v-if="titleParts.separator" class="movie-hero__title-colon">{{ titleParts.separator }}</span> <span class="movie-hero__title-accent">{{ titleParts.accent }}</span></template>
        </h1>

        <p v-if="movie.tagline" class="movie-hero__tagline">{{ movie.tagline }}</p>

        <div class="movie-hero__chips">
          <span v-if="ratingScore != null" class="chip gold">
            ★ {{ ratingScore }}
            <span class="movie-hero__chip-sub">Critic</span>
          </span>
          <span class="chip">PG-13</span>
          <span
            v-for="genre in movie.genres.slice(0, 2)"
            :key="genre.id"
            class="chip"
          >
            {{ genre.name }}
          </span>
          <span class="chip">{{ formatRuntime(movie.runtime) }}</span>
          <span v-if="releaseYear" class="chip">{{ releaseYear }}</span>
          <span class="chip gold">70mm print</span>
          <span class="chip">Dolby Atmos</span>
        </div>

        <div class="movie-hero__stats">
          <div>
            <span>Director</span>
            <b>{{ STUB_CREW.director }}</b>
          </div>
          <div>
            <span>Writer</span>
            <b>{{ STUB_CREW.writer }}</b>
          </div>
          <div>
            <span>Cinematographer</span>
            <b>{{ STUB_CREW.cinematographer }}</b>
          </div>
          <div>
            <span>Composer</span>
            <b>{{ STUB_CREW.composer }}</b>
          </div>
          <div>
            <span>Studio</span>
            <b class="movie-hero__stat-gold">{{ STUB_CREW.studio }}</b>
          </div>
        </div>

        <div class="movie-hero__cta-row">
          <CvButton variant="primary" @click="scrollTo('showtimes')">
            <template #icon-left>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 002 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z" />
              </svg>
            </template>
            Get Tickets
          </CvButton>
          <CvButton
            v-if="movie.trailerKey"
            variant="tertiary"
            @click="scrollTo('trailer')"
          >
            <template #icon-left>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M8 5v14l11-7z" />
              </svg>
            </template>
            Watch Trailer
          </CvButton>
          <button type="button" class="icon-btn" aria-label="Add to watchlist">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z" />
            </svg>
          </button>
          <button type="button" class="icon-btn" aria-label="Share" @click="handleShare">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.movie-hero {
  position: relative;
  min-height: 47.5rem;
  padding: var(--space-3xl) var(--space-2xl) var(--space-2xl);
  overflow: hidden;
}

.movie-hero__bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    radial-gradient(ellipse 70% 90% at 70% 20%, rgba(196, 112, 64, 0.42), transparent 55%),
    radial-gradient(ellipse 60% 60% at 30% 70%, rgba(var(--primary-container-rgb), 0.55), transparent 60%),
    linear-gradient(180deg, #1a0a05 0%, #2a1408 30%, #1a0d06 60%, var(--surface-container-lowest) 100%);
}

.movie-hero__bg::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    repeating-linear-gradient(90deg, transparent 0 7.375rem, rgba(var(--secondary-rgb), 0.04) 7.375rem 7.4375rem),
    repeating-linear-gradient(0deg, transparent 0 3.75rem, rgba(204, 198, 182, 0.02) 3.75rem 3.8125rem);
}

.movie-hero__bg::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 50%, var(--surface) 100%);
}

.movie-hero__backdrop-art {
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background:
    radial-gradient(ellipse 30% 40% at 60% 40%, rgba(255, 180, 120, 0.08), transparent 60%),
    radial-gradient(ellipse 40% 20% at 25% 55%, rgba(100, 50, 20, 0.35), transparent 70%);
}

.movie-hero__backdrop-art::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.08) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
}

.movie-hero__backdrop-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  z-index: 1;
  opacity: 0.35;
  mix-blend-mode: luminosity;
}

.movie-hero__grain-label {
  position: absolute;
  left: var(--space-2xl);
  top: var(--space-md);
  z-index: 3;
  display: flex;
  align-items: center;
  gap: var(--space-md);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-hero__grain-label b {
  color: var(--secondary);
  font-weight: 500;
}

.movie-hero__rule {
  width: 3rem;
  height: var(--border-hairline);
  background: var(--outline-variant);
  opacity: 0.6;
  display: inline-block;
}

.movie-hero__telemetry {
  position: absolute;
  top: var(--space-md);
  right: var(--space-2xl);
  z-index: 3;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.3rem;
  font-size: 0.625rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
}

.movie-hero__clock {
  font-family: var(--font-display);
  font-size: 1.125rem;
  letter-spacing: 0.04em;
  color: var(--on-surface);
  font-variant-numeric: tabular-nums;
}

.movie-hero__inner {
  position: relative;
  z-index: 2;
  max-width: 90rem;
  margin-inline: auto;
  width: 100%;
  display: grid;
  grid-template-columns: 21.25rem 1fr;
  gap: var(--space-2xl);
  align-items: end;
  padding-top: var(--space-2xl);
  min-height: 37.5rem;
  animation: movie-hero-reveal var(--duration-cinematic) var(--ease-enter) both;
}

@keyframes movie-hero-reveal {
  from { opacity: 0; transform: translateY(1rem); }
  to { opacity: 1; transform: translateY(0); }
}

/* ——— Poster ——— */
.movie-hero__poster-wrap {
  position: relative;
}

.movie-hero__poster {
  aspect-ratio: 2 / 3;
  border-radius: var(--radius-card);
  overflow: hidden;
  position: relative;
  box-shadow: 0 1.25rem 2.5rem rgba(0, 0, 0, 0.6);
  background: radial-gradient(ellipse at 30% 30%, #c47040, #6b2818 55%, #1f0d08 100%);
}

.movie-hero__poster::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.07) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
  z-index: 2;
  pointer-events: none;
}

.movie-hero__poster-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
  z-index: 1;
}

.movie-hero__poster-glyph {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 11rem;
  line-height: 0.8;
  color: rgba(255, 255, 255, 0.08);
  letter-spacing: -0.05em;
  z-index: 1;
}

.movie-hero__poster-badge {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  padding: 0.3rem 0.55rem;
  background: var(--secondary);
  color: var(--surface);
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  font-weight: 500;
  border-radius: var(--radius-sm);
  z-index: 3;
}

.movie-hero__poster-foot {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: var(--space-lg);
  background: linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.85));
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  z-index: 3;
}

.movie-hero__poster-foot-s {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-hero__poster-foot-t {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-style: italic;
  line-height: 1;
  letter-spacing: -0.015em;
  color: var(--on-surface);
}

.movie-hero__poster-meta {
  margin-top: var(--space-md);
  display: flex;
  justify-content: space-between;
  padding: var(--space-md) var(--space-sm);
  border-top: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.3);
  border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.3);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-hero__poster-meta span {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.movie-hero__poster-meta b {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  letter-spacing: -0.01em;
  color: var(--on-surface);
  font-weight: 500;
  text-transform: none;
}

/* ——— Title column ——— */
.movie-hero__title-col {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding-bottom: var(--space-md);
}

.movie-hero__t-meta {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  flex-wrap: wrap;
}

.movie-hero__t-dot {
  width: 0.375rem;
  height: 0.375rem;
  background: var(--secondary);
  border-radius: var(--radius-full);
  display: inline-block;
}

.movie-hero__t-meta b {
  color: var(--secondary);
  font-weight: 500;
}

.movie-hero__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(3rem, 7.5vw, 7rem);
  line-height: 0.9;
  letter-spacing: -0.035em;
  color: var(--on-surface);
  text-wrap: balance;
  margin: 0;
}

.movie-hero__title-colon {
  font-style: italic;
  color: var(--tertiary);
  font-weight: 400;
}

.movie-hero__title-accent {
  color: var(--secondary);
  font-style: italic;
}

.movie-hero__tagline {
  font-size: 1.25rem;
  color: var(--tertiary);
  max-width: 52ch;
  line-height: 1.5;
  text-wrap: pretty;
  font-style: italic;
  margin: 0;
}

.movie-hero__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.movie-hero__chip-sub {
  color: var(--on-tertiary-fixed-variant);
}

.movie-hero__stats {
  display: grid;
  grid-template-columns: repeat(5, auto);
  gap: var(--space-xl);
  margin-top: var(--space-md);
  padding-top: var(--space-lg);
  border-top: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-hero__stats > div {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.movie-hero__stats b {
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--on-surface);
  font-weight: 500;
  letter-spacing: -0.01em;
  text-transform: none;
}

.movie-hero__stat-gold {
  color: var(--secondary) !important;
}

.movie-hero__cta-row {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  flex-wrap: wrap;
  margin-top: var(--space-md);
}

/* ——— Responsive ——— */
@media (max-width: 68.75rem) {
  .movie-hero__inner {
    grid-template-columns: 1fr;
  }

  .movie-hero__stats {
    grid-template-columns: repeat(3, 1fr);
    row-gap: var(--space-md);
  }

  .movie-hero__poster-wrap {
    max-width: 21.25rem;
  }
}

@media (max-width: 39.999rem) {
  .movie-hero {
    padding-inline: var(--space-md);
    min-height: auto;
  }

  .movie-hero__grain-label,
  .movie-hero__telemetry {
    display: none;
  }

  .movie-hero__stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .movie-hero__inner {
    animation: none;
  }
}
</style>
