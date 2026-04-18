<script setup lang="ts">
interface TickerItem {
  text: string
  label?: string
  href?: string
}

const props = defineProps<{
  items: TickerItem[]
}>()

const isPaused = ref(false)

function togglePause() {
  isPaused.value = !isPaused.value
}

const linkedItems = computed(() => props.items.filter((item) => item.href))
const plainItems = computed(() => props.items.filter((item) => !item.href))
</script>

<template>
  <aside class="neural-ticker" aria-label="Now showing updates" aria-live="off">
    <!-- Pulsing "On Air" signal -->
    <div class="neural-ticker__signal" aria-hidden="true">
      <span class="neural-ticker__signal-dot" />
      <span class="neural-ticker__signal-label">On Air</span>
    </div>

    <!-- Screen reader accessible alternative (preserves links) -->
    <ul v-if="linkedItems.length > 0" class="sr-only">
      <li v-for="(item, i) in items" :key="i">
        <NuxtLink v-if="item.href" :to="item.href">{{ item.label ? `${item.label}: ` : '' }}{{ item.text }}</NuxtLink>
        <span v-else>{{ item.label ? `${item.label}: ` : '' }}{{ item.text }}</span>
      </li>
    </ul>
    <span v-else class="sr-only">
      {{ plainItems.map((item) => (item.label ? `${item.label}: ${item.text}` : item.text)).join(' — ') }}
    </span>

    <!-- Visual scrolling content (hidden from screen readers) -->
    <div class="neural-ticker__track-wrapper" aria-hidden="true">
      <div
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
            <span v-if="item.label" class="neural-ticker__item-label">{{ item.label }}</span>
            <NuxtLink v-if="item.href" :to="item.href" class="neural-ticker__link" tabindex="-1">
              {{ item.text }}
            </NuxtLink>
            <span v-else>{{ item.text }}</span>
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
  background-color: var(--surface-container-lowest);
  border-bottom: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2); /* token-exception: sub-pixel edge */
  z-index: var(--z-ticker);
  position: sticky;
  top: 4rem; /* sits directly below the 4rem fixed header */
  overflow: hidden;
}

/* On Air signal — reactor-light badge */
.neural-ticker__signal {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  height: 100%;
  padding-inline: 0.875rem;
  background-color: var(--primary-container);
  color: var(--primary);
  font-family: var(--font-body);
  font-size: 0.625rem;
  font-weight: 500;
  letter-spacing: 0.24em;
  text-transform: uppercase;
}

.neural-ticker__signal-dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%; /* token-exception: signal dot */
  background-color: var(--primary);
  animation: neural-ticker-pulse 1.8s ease-in-out infinite;
}

@keyframes neural-ticker-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

.neural-ticker__signal-label {
  line-height: 1;
}

/* Track */
.neural-ticker__track-wrapper {
  flex: 1;
  position: relative;
  overflow: hidden;
}

.neural-ticker__track-wrapper::before,
.neural-ticker__track-wrapper::after {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  width: 3rem;
  z-index: 2;
  pointer-events: none;
}

.neural-ticker__track-wrapper::before {
  left: 0;
  background: linear-gradient(90deg, var(--surface-container-lowest), transparent);
}

.neural-ticker__track-wrapper::after {
  right: 0;
  background: linear-gradient(270deg, var(--surface-container-lowest), transparent);
}

.neural-ticker__track {
  display: flex;
  white-space: nowrap;
  animation: neural-ticker-scroll var(--ticker-duration, 80s) linear infinite;
}

.neural-ticker__track--paused {
  animation-play-state: paused;
}

@keyframes neural-ticker-scroll {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}

@media (prefers-reduced-motion: reduce) {
  .neural-ticker__track {
    animation: none;
    flex-wrap: wrap;
    white-space: normal;
    gap: var(--space-xs);
  }

  .neural-ticker__signal-dot {
    animation: none;
  }
}

.neural-ticker__item {
  display: inline-flex;
  align-items: center;
  gap: 0.625rem;
  padding-inline: 1.25rem;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.neural-ticker__item::after {
  content: '◦';
  color: var(--outline-variant);
  margin-left: 1.25rem;
}

.neural-ticker__item-label {
  color: var(--secondary);
  font-weight: 500;
}

.neural-ticker__link {
  color: inherit;
  text-decoration: none;
}

.neural-ticker__link:hover {
  color: var(--secondary);
}

/* Pause control */
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
