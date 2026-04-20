<script setup lang="ts">
import type { CastMember } from '~/types/movie'

const props = defineProps<{
  cast: CastMember[]
}>()

// Limit to 6 principal cast (matching design's 6-col grid)
const principal = computed(() => props.cast.slice(0, 6))

// Deterministic atmospheric gradient for cast cards without a profile image,
// seeded off the member id so it stays stable across renders.
function gradientFor(id: number): string {
  const hue = (id * 53) % 360
  return `linear-gradient(160deg, hsl(${hue}deg 20% 35%), hsl(${(hue + 20) % 360}deg 25% 12%))`
}

function glyphFor(name: string): string {
  return name.trim().charAt(0).toUpperCase() || '·'
}
</script>

<template>
  <div v-if="principal.length > 0" class="movie-cast">
    <div class="bay-eyebrow">Cast · Principal</div>
    <h2 class="bay-title">The <em>ensemble.</em></h2>

    <div class="movie-cast__grid">
      <div
        v-for="member in principal"
        :key="member.id"
        class="movie-cast__card"
      >
        <div
          class="movie-cast__portrait"
          :style="!member.profileUrl ? { background: gradientFor(member.id) } : undefined"
        >
          <img
            v-if="member.profileUrl"
            :src="member.profileUrl"
            :alt="member.name"
            class="movie-cast__photo"
            loading="lazy"
          />
          <span v-else class="movie-cast__glyph" aria-hidden="true">
            {{ glyphFor(member.name) }}
          </span>
        </div>
        <div class="movie-cast__name">{{ member.name }}</div>
        <div class="movie-cast__role">{{ member.character }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.movie-cast {
  display: flex;
  flex-direction: column;
}

.movie-cast__grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: var(--space-md);
  margin-top: var(--space-xl);
}

.movie-cast__card {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  transition: transform 200ms var(--ease-standard);
}

.movie-cast__card:hover {
  transform: translateY(-0.125rem);
}

.movie-cast__portrait {
  aspect-ratio: 3 / 4;
  border-radius: 0.125rem;
  position: relative;
  overflow: hidden;
  background-color: var(--surface-container-high);
}

.movie-cast__portrait::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.08) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
  pointer-events: none;
  z-index: 2;
}

.movie-cast__photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
  display: block;
}

.movie-cast__glyph {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-size: 3rem;
  color: rgba(255, 255, 255, 0.18);
  font-style: italic;
  letter-spacing: -0.02em;
}

.movie-cast__name {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--on-surface);
  line-height: 1.2;
  letter-spacing: -0.005em;
}

.movie-cast__role {
  font-size: 0.6875rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

@media (max-width: 68.75rem) {
  .movie-cast__grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 39.999rem) {
  .movie-cast__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .movie-cast__card {
    transition: none;
  }
}
</style>
