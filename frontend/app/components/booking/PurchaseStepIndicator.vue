<script setup lang="ts">
const props = withDefaults(defineProps<{
  currentStep: 1 | 2 | 3
  completedSteps: number[]
  navigableSteps?: number[]
}>(), {
  navigableSteps: undefined,
})

const emit = defineEmits<{
  navigate: [step: number]
}>()

const resolvedNavigableSteps = computed(() =>
  props.navigableSteps ?? props.completedSteps,
)

const steps = [
  { number: 1, label: 'Pick Your Seats' },
  { number: 2, label: 'Add Food & Pay' },
  { number: 3, label: "You're In" },
] as const

function isCompleted(step: number): boolean {
  return props.completedSteps.includes(step)
}

function isNavigable(step: number): boolean {
  return resolvedNavigableSteps.value.includes(step)
}

function isCurrent(step: number): boolean {
  return props.currentStep === step
}

function isFuture(step: number): boolean {
  return step > props.currentStep && !isCompleted(step)
}

function handleClick(step: number) {
  if (isNavigable(step) && !isCurrent(step)) {
    emit('navigate', step)
  }
}
</script>

<template>
  <nav class="purchase-steps" aria-label="Purchase steps">
    <ol class="purchase-steps__list">
      <li
        v-for="(step, index) in steps"
        :key="step.number"
        class="purchase-steps__item"
      >
        <!-- Connector line (between steps) -->
        <span
          v-if="index > 0"
          class="purchase-steps__connector"
          :class="{ 'purchase-steps__connector--completed': isCompleted(step.number) }"
        />

        <!-- Step indicator -->
        <component
          :is="isNavigable(step.number) && !isCurrent(step.number) ? 'button' : 'span'"
          :type="isNavigable(step.number) && !isCurrent(step.number) ? 'button' : undefined"
          class="purchase-steps__step"
          :class="{
            'purchase-steps__step--current': isCurrent(step.number),
            'purchase-steps__step--completed': isCompleted(step.number) && !isCurrent(step.number),
            'purchase-steps__step--future': isFuture(step.number),
            'purchase-steps__step--navigable': isNavigable(step.number) && !isCurrent(step.number),
          }"
          :aria-current="isCurrent(step.number) ? 'step' : undefined"
          :aria-disabled="!isNavigable(step.number) && !isCurrent(step.number) ? 'true' : undefined"
          @click="handleClick(step.number)"
        >
          <span class="purchase-steps__number">
            <template v-if="isCompleted(step.number) && !isCurrent(step.number)">
              <CvIcon name="check" size="sm" />
            </template>
            <template v-else>
              {{ step.number }}
            </template>
          </span>
          <span class="purchase-steps__label">{{ step.label }}</span>
        </component>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.purchase-steps__list {
  display: flex;
  align-items: center;
  list-style: none;
  padding: 0;
  margin: 0;
  gap: 0;
}

.purchase-steps__item {
  display: flex;
  align-items: center;
}

/* Connector line between steps */
.purchase-steps__connector {
  display: block;
  width: 3rem;
  height: 0.0625rem; /* token-exception: sub-pixel connector line */
  background-color: rgb(var(--outline-variant-rgb) / 0.15);
  flex-shrink: 0;
}

@media (min-width: 40rem) {
  .purchase-steps__connector {
    width: 5rem;
  }
}

.purchase-steps__connector--completed {
  background-color: var(--secondary);
}

/* Step element */
.purchase-steps__step {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  background: none;
  border: none;
  padding: var(--space-xs) 0;
  font-family: var(--font-body);
  font-size: var(--type-label-lg);
  line-height: 1.4;
  color: var(--outline-variant);
  cursor: default;
  white-space: nowrap;
}

.purchase-steps__step--current {
  color: var(--secondary);
  position: relative;
}

.purchase-steps__step--current::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 0.125rem; /* token-exception: decorative underline */
  background-color: var(--secondary);
}

.purchase-steps__step--completed {
  color: var(--secondary);
}

.purchase-steps__step--future {
  color: var(--outline-variant);
}

.purchase-steps__step--navigable {
  cursor: pointer;
}

.purchase-steps__step--navigable:hover {
  color: var(--on-surface);
}

/* Step number circle */
.purchase-steps__number {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  font-size: var(--type-label-md);
  flex-shrink: 0;
}

/* Hide labels on very small screens, keep numbers */
@media (max-width: 39.999rem) {
  .purchase-steps__label {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }
}
</style>
