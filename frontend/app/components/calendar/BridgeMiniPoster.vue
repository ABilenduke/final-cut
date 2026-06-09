<script setup lang="ts">
import { computed } from 'vue'
import { hashToHue, initialsFrom } from '~/utils/posterFallback'

const props = withDefaults(defineProps<{
  title: string
  posterUrl?: string | null
  size?: 'sm' | 'md'
}>(), {
  posterUrl: null,
  size: 'md',
})

const hue = computed(() => hashToHue(props.title))

const initials = computed(() => initialsFrom(props.title))

const gradientStyle = computed(() => ({
  '--bridge-poster-hue': `${hue.value}`,
}))
</script>

<template>
  <div
    class="bridge-mini-poster"
    :class="`bridge-mini-poster--${size}`"
    :style="gradientStyle"
  >
    <img
      v-if="posterUrl"
      :src="posterUrl"
      :alt="`${title} poster`"
      class="bridge-mini-poster__img"
      loading="lazy"
    >
    <template v-else>
      <span class="bridge-mini-poster__grain" aria-hidden="true" />
      <span class="bridge-mini-poster__mark">{{ initials }}</span>
    </template>
  </div>
</template>

<style scoped>
.bridge-mini-poster {
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background:
    linear-gradient(
      135deg,
      hsl(var(--bridge-poster-hue, 0) 40% 22%),
      hsl(var(--bridge-poster-hue, 0) 30% 8%)
    );
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
  flex-shrink: 0;
}

.bridge-mini-poster--sm {
  width: 100%;
  height: 100%;
}

.bridge-mini-poster--md {
  width: 4rem;
  height: 6rem;
}

.bridge-mini-poster__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
}

.bridge-mini-poster__grain {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    repeating-linear-gradient(
      0deg,
      transparent 0 0.125rem,
      rgba(255, 255, 255, 0.018) 0.125rem 0.1875rem
    ),
    radial-gradient(
      ellipse at 30% 20%,
      rgba(255, 255, 255, 0.08),
      transparent 70%
    );
  mix-blend-mode: overlay;
}

.bridge-mini-poster__mark {
  position: absolute;
  top: 0.3rem;
  right: 0.4rem;
  font-family: var(--font-display);
  font-weight: 600;
  font-size: 0.875rem;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  opacity: 0.85;
  text-shadow: 0 0 0.75rem rgba(0, 0, 0, 0.6);
}
</style>
