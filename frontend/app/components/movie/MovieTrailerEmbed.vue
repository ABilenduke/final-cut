<script setup lang="ts">
const props = defineProps<{
  trailerKey: string | null
  title: string
}>()

// TODO(backend): replace placeholder clips with real clip/featurette data when added
type Clip = {
  id: string
  label: string
  sub: string
  duration: string
  trailerKey: string | null
  thumbGradient: string
}

const clips = computed<Clip[]>(() => [
  {
    id: 'trailer',
    label: 'Official Trailer',
    sub: 'Warner · Legendary',
    duration: '2:38',
    trailerKey: props.trailerKey,
    thumbGradient: 'radial-gradient(ellipse at 40% 30%, #c47040, #5b2018 60%, #0f0604)',
  },
  {
    id: 'teaser',
    label: 'Teaser — First Look',
    sub: 'Released 2026',
    duration: '1:14',
    trailerKey: null,
    thumbGradient: 'radial-gradient(ellipse at 70% 40%, #7a4020, #2a1008 60%, #050302)',
  },
  {
    id: 'featurette',
    label: 'Featurette — Behind the Score',
    sub: 'With composer · 6:02',
    duration: '6:02',
    trailerKey: null,
    thumbGradient: 'radial-gradient(ellipse at 30% 60%, #8a6a40, #3a2818 60%, #100802)',
  },
  {
    id: 'camera',
    label: 'Behind the Camera',
    sub: 'Cinematography feature',
    duration: '4:47',
    trailerKey: null,
    thumbGradient: 'radial-gradient(ellipse at 50% 50%, #5a5040, #282010 60%, #080402)',
  },
  {
    id: 'press',
    label: 'Press Conference',
    sub: 'Cast & director',
    duration: '28:14',
    trailerKey: null,
    thumbGradient: 'radial-gradient(ellipse at 60% 40%, #3a4060, #141828 60%, #04060a)',
  },
])

const activeClipId = ref<string>('trailer')
const activeClip = computed(() => clips.value.find(c => c.id === activeClipId.value) ?? clips.value[0])

const isPlaying = ref(false)

function selectClip(id: string) {
  activeClipId.value = id
  isPlaying.value = false // reset when switching clips
}

function play() {
  if (activeClip.value.trailerKey) {
    isPlaying.value = true
  }
}
</script>

<template>
  <div class="bay-eyebrow">Trailer · Official</div>
  <h2 class="bay-title">The <em>first look</em> at {{ title }}.</h2>
  <div class="movie-trailer__spacer" aria-hidden="true" />

  <div class="movie-trailer__grid">
    <!-- Trailer stage -->
    <div class="movie-trailer__stage" :class="{ 'movie-trailer__stage--playing': isPlaying }">
      <iframe
        v-if="isPlaying && activeClip.trailerKey"
        :src="`https://www.youtube.com/embed/${activeClip.trailerKey}?autoplay=1&rel=0`"
        :title="`${title} trailer`"
        loading="lazy"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
        class="movie-trailer__iframe"
      />
      <template v-else>
        <div class="movie-trailer__stage-gradient" :style="{ background: activeClip.thumbGradient }" aria-hidden="true" />
        <div class="movie-trailer__stage-grain" aria-hidden="true" />
        <div class="movie-trailer__stage-overlay">
          <div class="movie-trailer__stage-top">
            <span>HD · 4K · 5.1</span>
            <span class="movie-trailer__reel">
              <b>Reel 047</b>&nbsp;Loaded
            </span>
          </div>
          <div class="movie-trailer__stage-mid">
            <button
              type="button"
              class="movie-trailer__play"
              :aria-label="activeClip.trailerKey ? 'Play trailer' : 'Trailer unavailable'"
              :disabled="!activeClip.trailerKey"
              @click="play"
            >
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M8 5v14l11-7z" />
              </svg>
            </button>
            <span class="movie-trailer__caption">
              {{ activeClip.trailerKey ? 'Press to begin' : 'Trailer unavailable' }}
            </span>
          </div>
          <div class="movie-trailer__stage-bottom">
            <span class="movie-trailer__stage-title">{{ activeClip.label }}</span>
            <span>{{ activeClip.duration }}</span>
          </div>
          <div class="movie-trailer__scrub" aria-hidden="true" />
        </div>
      </template>
    </div>

    <!-- Clips sidebar -->
    <div class="movie-trailer__clips">
      <div class="movie-trailer__clips-h">Clips &amp; Featurettes</div>
      <button
        v-for="clip in clips"
        :key="clip.id"
        type="button"
        class="movie-trailer__clip"
        :class="{ 'movie-trailer__clip--active': clip.id === activeClipId }"
        :aria-pressed="clip.id === activeClipId"
        @click="selectClip(clip.id)"
      >
        <span class="movie-trailer__clip-thumb" :style="{ background: clip.thumbGradient }">
          <span class="movie-trailer__clip-play" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M8 5v14l11-7z" />
            </svg>
          </span>
        </span>
        <span class="movie-trailer__clip-body">
          <span class="movie-trailer__clip-title">{{ clip.label }}</span>
          <span class="movie-trailer__clip-sub">{{ clip.sub }}</span>
        </span>
        <span class="movie-trailer__clip-dur">{{ clip.duration }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.movie-trailer__spacer {
  height: var(--space-xl);
}

.movie-trailer__grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: var(--space-xl);
}

/* ——— Stage ——— */
.movie-trailer__stage {
  aspect-ratio: 16 / 9;
  border-radius: var(--radius-card);
  overflow: hidden;
  position: relative;
  cursor: default;
  transition: transform var(--duration-standard) var(--ease-standard);
}

.movie-trailer__stage-gradient {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 35% 40%, rgba(196, 112, 64, 0.5), transparent 50%),
    radial-gradient(ellipse at 75% 60%, rgba(var(--primary-container-rgb), 0.55), transparent 60%),
    linear-gradient(180deg, #1a0a05, #0a0605);
}

.movie-trailer__stage-grain {
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.07) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
  pointer-events: none;
}

.movie-trailer__iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border: none;
}

.movie-trailer__stage-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: var(--space-lg);
}

.movie-trailer__stage-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgba(229, 226, 225, 0.7);
}

.movie-trailer__reel {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.movie-trailer__reel::before {
  content: '';
  width: 0.375rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--secondary);
  animation: movie-trailer-blink 1.6s ease-in-out infinite;
}

.movie-trailer__reel b {
  color: var(--secondary);
  font-weight: 500;
}

@keyframes movie-trailer-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

.movie-trailer__stage-mid {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: grid;
  place-items: center;
  gap: 0.875rem;
}

.movie-trailer__play {
  width: 5.5rem;
  height: 5.5rem;
  border-radius: var(--radius-full);
  background: rgba(20, 20, 20, 0.55);
  backdrop-filter: blur(0.875rem);
  -webkit-backdrop-filter: blur(0.875rem);
  border: var(--border-hairline) solid rgba(var(--secondary-rgb), 0.4);
  color: var(--secondary);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    transform var(--duration-standard) var(--ease-standard),
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.movie-trailer__play:hover:not([disabled]) {
  background: var(--secondary);
  color: var(--surface);
  border-color: var(--secondary);
  transform: scale(1.05);
}

.movie-trailer__play[disabled] {
  opacity: 0.4;
  cursor: not-allowed;
}

.movie-trailer__play svg {
  width: 1.75rem;
  height: 1.75rem;
  margin-left: 0.25rem;
}

.movie-trailer__play:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.25rem;
}

.movie-trailer__caption {
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: center;
}

.movie-trailer__stage-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  font-size: 0.625rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(229, 226, 225, 0.7);
}

.movie-trailer__stage-title {
  font-family: var(--font-display);
  font-size: 1.375rem;
  letter-spacing: -0.015em;
  text-transform: none;
  color: var(--on-surface);
  font-style: italic;
}

.movie-trailer__scrub {
  position: absolute;
  left: var(--space-lg);
  right: var(--space-lg);
  bottom: var(--space-lg);
  height: 0.125rem;
  background: rgba(229, 226, 225, 0.15);
  border-radius: var(--radius-sm);
}

.movie-trailer__scrub::before {
  content: '';
  position: absolute;
  inset: 0;
  width: 18%;
  background: var(--secondary);
  border-radius: var(--radius-sm);
}

/* ——— Clips list ——— */
.movie-trailer__clips {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.movie-trailer__clips-h {
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.movie-trailer__clips-h::before {
  content: '—';
  color: var(--secondary);
}

.movie-trailer__clip {
  display: grid;
  grid-template-columns: 6rem 1fr auto;
  gap: 0.875rem;
  align-items: center;
  padding: 0.65rem;
  border-radius: var(--radius-sm);
  border: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  transition: border-color var(--duration-standard), background-color var(--duration-standard);
}

.movie-trailer__clip:hover {
  border-color: rgba(var(--secondary-rgb), 0.3);
  background: rgba(42, 42, 42, 0.3);
}

.movie-trailer__clip--active {
  border-color: var(--secondary);
  background: rgba(var(--secondary-rgb), 0.06);
}

.movie-trailer__clip:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.movie-trailer__clip-thumb {
  aspect-ratio: 16 / 9;
  border-radius: var(--radius-sm);
  position: relative;
  overflow: hidden;
}

.movie-trailer__clip-thumb::after {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.1) 0.125rem 0.1875rem
  );
}

.movie-trailer__clip-play {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: rgba(255, 255, 255, 0.8);
  z-index: 1;
}

.movie-trailer__clip-play svg {
  width: 0.875rem;
  height: 0.875rem;
}

.movie-trailer__clip-body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.movie-trailer__clip-title {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  letter-spacing: -0.01em;
  line-height: 1.2;
  color: var(--on-surface);
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.movie-trailer__clip-sub {
  font-size: 0.6875rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-trailer__clip-dur {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.04em;
  font-variant-numeric: tabular-nums;
}

.movie-trailer__clip--active .movie-trailer__clip-dur {
  color: var(--secondary);
}

@media (max-width: 68.75rem) {
  .movie-trailer__grid {
    grid-template-columns: 1fr;
  }
}

@media (prefers-reduced-motion: reduce) {
  .movie-trailer__reel::before {
    animation: none;
  }

  .movie-trailer__play {
    transition: none;
  }
}
</style>
