<script setup lang="ts">
const props = defineProps<{
  items: Array<{ text: string; href?: string }>
}>()

const isPaused = ref(false)
const tickerTrackEl = ref<HTMLElement | null>(null)

function togglePause() {
  isPaused.value = !isPaused.value
}

// Items with links get accessible list; plain text items get flat text
const linkedItems = computed(() => props.items.filter((item) => item.href))
const plainItems = computed(() => props.items.filter((item) => !item.href))
</script>

<template>
  <aside class="neural-ticker" aria-label="Now showing updates" aria-live="off">
    <!-- Screen reader accessible alternative (preserves links) -->
    <ul v-if="linkedItems.length > 0" class="sr-only">
      <li v-for="(item, i) in items" :key="i">
        <NuxtLink v-if="item.href" :to="item.href">{{ item.text }}</NuxtLink>
        <span v-else>{{ item.text }}</span>
      </li>
    </ul>
    <span v-else class="sr-only">
      {{ plainItems.map((item) => item.text).join(' — ') }}
    </span>

    <!-- Visual scrolling content (hidden from screen readers) -->
    <div class="neural-ticker__track-wrapper" aria-hidden="true">
      <div
        ref="tickerTrackEl"
        class="neural-ticker__track"
        :class="{ 'neural-ticker__track--paused': isPaused }"
      >
        <!-- Duplicate items for seamless loop -->
        <template v-for="(_, dupeIndex) in 2" :key="dupeIndex">
          <span
            v-for="(item, index) in items"
            :key="`${dupeIndex}-${index}`"
            class="neural-ticker__item"
          >
            <NuxtLink v-if="item.href" :to="item.href" class="neural-ticker__link" tabindex="-1">
              {{ item.text }}
            </NuxtLink>
            <span v-else>{{ item.text }}</span>
            <span class="neural-ticker__separator" aria-hidden="true">&middot;</span>
          </span>
        </template>
      </div>
    </div>

    <!-- Pause/play control -->
    <button
      type="button"
      class="neural-ticker__control"
      :aria-label="isPaused ? 'Play ticker' : 'Pause ticker'"
      :aria-pressed="isPaused"
      @click="togglePause"
    >
      <CvIcon :name="isPaused ? 'play' : 'pause'" size="sm" />
    </button>
  </aside>
</template>

<style scoped>
.neural-ticker {
  display: flex;
  align-items: center;
  height: 2rem;
  background-color: var(--surface-container);
  z-index: var(--z-ticker);
  position: sticky;
  top: 4rem; /* sits directly below the 4rem fixed header */
  overflow: hidden;
}

.neural-ticker__track-wrapper {
  flex: 1;
  overflow: hidden;
}

.neural-ticker__track {
  display: flex;
  white-space: nowrap;
  animation: neural-ticker-scroll var(--ticker-duration, 60s) linear infinite;
}

.neural-ticker__track--paused {
  animation-play-state: paused;
}

@keyframes neural-ticker-scroll {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .neural-ticker__track {
    animation: none;
    flex-wrap: wrap;
    white-space: normal;
    gap: var(--space-xs);
  }
}

.neural-ticker__item {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding-inline: var(--space-sm);
  font-family: var(--font-body);
  font-size: var(--type-label-sm);
  line-height: 1.4;
  color: var(--on-tertiary-fixed-variant);
}

.neural-ticker__link {
  color: inherit;
  text-decoration: none;
}

.neural-ticker__link:hover {
  color: var(--secondary);
  text-decoration: underline;
}

.neural-ticker__separator {
  color: var(--outline-variant);
  opacity: 0.4; /* token-exception: subtle visual separator */
}

.neural-ticker__control {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  background: none;
  border: none;
  color: var(--on-tertiary-fixed-variant);
  cursor: pointer;
}

.neural-ticker__control:hover {
  color: var(--on-surface);
}
</style>
