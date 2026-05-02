<script setup lang="ts">
import type { ProgrammePairing } from '~/types/programme-pairing'

const props = defineProps<{
  pairing: ProgrammePairing
  added: boolean
}>()

const emit = defineEmits<{
  toggle: []
}>()

const ROMAN = ['I.', 'II.', 'III.', 'IV.', 'V.'] as const

const coursesTotal = computed<number>(() =>
  props.pairing.courses.reduce((sum, c) => sum + c.price, 0),
)

const savings = computed<number>(() =>
  Math.max(0, coursesTotal.value - props.pairing.bundlePrice),
)

const buttonLabel = computed<string>(() => (props.added ? '✓ Added' : 'Add the flight'))

const buttonAriaLabel = computed<string>(() =>
  props.added
    ? `Remove the ${props.pairing.title} pairing from your tray`
    : `Add the ${props.pairing.title} pairing to your tray`,
)
</script>

<template>
  <section class="pairing" aria-label="Programme pairing">
    <div
      class="pairing__art"
      :style="props.pairing.gradient ? { background: props.pairing.gradient } : undefined"
      aria-hidden="true"
    >
      <span class="pairing__glyph">{{ props.pairing.glyph }}</span>
      <div class="pairing__watermark">
        <span>Reel · No. {{ props.pairing.number }}</span>
        <span>Pairing</span>
      </div>
    </div>

    <div class="pairing__body">
      <p class="pairing__tag">{{ props.pairing.tag }}</p>

      <h2 class="pairing__title">
        The <em>{{ props.pairing.titleAccent }}</em> {{ props.pairing.titleTail }}
      </h2>

      <p class="pairing__note">
        <span class="pairing__mark" aria-hidden="true">¶</span>
        {{ props.pairing.curatorNote }}
      </p>

      <ol class="pairing__items">
        <li v-for="(course, idx) in props.pairing.courses" :key="course.id">
          <span class="pairing__n">{{ ROMAN[idx] ?? `${idx + 1}.` }}</span>
          <span class="pairing__nm">{{ course.name }}</span>
          <span class="pairing__pr">{{ formatCurrency(course.price) }}</span>
        </li>
      </ol>

      <p class="pairing__by">
        Curated by <b>{{ props.pairing.curatorName }}</b>
        <span v-if="props.pairing.curatorRole"> · {{ props.pairing.curatorRole }}</span>
      </p>
    </div>

    <div class="pairing__cta">
      <div class="pairing__price">
        <span v-if="savings > 0" class="pairing__was">{{ formatCurrency(coursesTotal) }}</span>
        <span class="pairing__now">{{ formatCurrency(props.pairing.bundlePrice) }}</span>
        <span v-if="props.pairing.validUntil" class="pairing__save">{{ props.pairing.validUntil }}</span>
      </div>
      <button
        type="button"
        class="pairing__btn"
        :class="{ 'pairing__btn--added': props.added }"
        :aria-pressed="props.added"
        :aria-label="buttonAriaLabel"
        @click="emit('toggle')"
      >
        {{ buttonLabel }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.pairing {
  position: relative;
  display: grid;
  grid-template-columns: 11.25rem minmax(0, 1fr) auto;
  gap: var(--space-lg);
  padding: var(--space-xl);
  background-color: var(--surface-container);
  border-radius: var(--radius-card);
  border: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.25);
  overflow: hidden;
}

.pairing::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    radial-gradient(ellipse 60% 80% at 85% 20%, rgba(196, 112, 64, 0.12), transparent 60%),
    radial-gradient(ellipse 40% 50% at 15% 80%, rgb(var(--primary-container-rgb) / 0.18), transparent 60%);
}

/* Poster art */
.pairing__art {
  position: relative;
  z-index: 1;
  aspect-ratio: 2 / 3;
  border-radius: var(--radius-sm);
  overflow: hidden;
  background: radial-gradient(ellipse at 30% 30%, #c47040, #6b2818 55%, #1f0d08 100%);
  box-shadow: var(--shadow-float);
}

.pairing__art::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.07) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
}

.pairing__glyph {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 5.5rem;
  line-height: 0.8;
  color: rgba(255, 255, 255, 0.12);
  letter-spacing: -0.05em;
}

.pairing__watermark {
  position: absolute;
  bottom: 0.6rem;
  left: 0.6rem;
  right: 0.6rem;
  font-family: var(--font-body);
  font-size: 0.5625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
  display: flex;
  justify-content: space-between;
}

/* Body */
.pairing__body {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  min-width: 0;
}

.pairing__tag {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--secondary);
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0;
}

.pairing__tag::after {
  content: '';
  flex: 1;
  height: var(--border-hairline);
  background-color: rgb(var(--secondary-rgb) / 0.2);
}

.pairing__title {
  font-family: var(--font-display);
  font-size: 1.875rem;
  font-weight: 500;
  letter-spacing: -0.02em;
  line-height: 1.05;
  margin: 0;
  color: var(--on-surface);
}

.pairing__title em {
  font-style: italic;
  color: var(--tertiary);
}

.pairing__note {
  font-family: var(--font-display);
  font-style: italic;
  font-size: 1rem;
  color: var(--tertiary);
  line-height: 1.5;
  text-wrap: pretty;
  max-width: 52ch;
  margin: 0;
}

.pairing__mark {
  color: var(--secondary);
  font-style: normal;
  margin-right: 0.25rem;
}

.pairing__items {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: var(--space-sm) 0 0;
  padding: 0;
}

.pairing__items li {
  display: flex;
  gap: 0.6rem;
  align-items: baseline;
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--tertiary);
}

.pairing__n {
  font-family: var(--font-display);
  font-size: 0.6875rem;
  color: var(--secondary);
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.1em;
  min-width: 1.25rem;
}

.pairing__nm {
  color: var(--on-surface);
  font-family: var(--font-display);
  letter-spacing: -0.005em;
}

.pairing__pr {
  margin-left: auto;
  color: var(--tertiary);
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
}

.pairing__by {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin: var(--space-sm) 0 0;
}

.pairing__by b {
  color: var(--on-surface);
  font-weight: 500;
}

/* CTA column */
.pairing__cta {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-md);
  padding-left: var(--space-md);
  border-left: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.3);
  min-width: 11.25rem;
}

.pairing__price {
  text-align: right;
}

.pairing__was {
  font-family: var(--font-display);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  text-decoration: line-through;
  font-variant-numeric: tabular-nums;
  display: block;
}

.pairing__now {
  display: block;
  font-family: var(--font-display);
  font-size: 2.25rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: -0.02em;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.pairing__save {
  display: block;
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: 0.4rem;
}

.pairing__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.1rem;
  background-color: var(--secondary);
  color: var(--surface);
  border: none;
  border-radius: var(--radius-sm);
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: var(--shadow-float);
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    transform var(--duration-micro) var(--ease-standard);
}

.pairing__btn:hover {
  background-color: var(--secondary-hover);
}

.pairing__btn:active {
  transform: scale(0.99);
}

.pairing__btn--added {
  background-color: var(--primary-container);
  color: var(--primary);
}

.pairing__btn--added:hover {
  background-color: var(--primary-container-hover);
}

@media (max-width: 68.75rem) {
  .pairing {
    grid-template-columns: 8.125rem 1fr;
  }

  .pairing__cta {
    grid-column: 1 / -1;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    padding-left: 0;
    border-left: none;
    border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.3);
    padding-top: var(--space-md);
  }
}

@media (max-width: 40rem) {
  .pairing {
    padding: var(--space-lg);
    gap: var(--space-md);
  }

  .pairing__title {
    font-size: 1.5rem;
  }

  .pairing__now {
    font-size: 1.75rem;
  }
}
</style>
