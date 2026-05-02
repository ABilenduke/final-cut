<script setup lang="ts">
import type { PurchaseStep } from '~/composables/usePurchaseStep'

const props = withDefaults(defineProps<{
  currentStep: PurchaseStep
  completedSteps: readonly PurchaseStep[]
  navigableSteps?: readonly PurchaseStep[]
}>(), {
  navigableSteps: undefined,
})

const emit = defineEmits<{
  navigate: [step: PurchaseStep]
}>()

const resolvedNavigableSteps = computed(() =>
  props.navigableSteps ?? props.completedSteps,
)

const steps = [
  { number: 1, label: 'Seats' },
  { number: 2, label: 'Snacks & Bar' },
  { number: 3, label: 'Payment' },
  { number: 4, label: 'Confirmation' },
] as const

function isCompleted(step: PurchaseStep): boolean {
  return props.completedSteps.includes(step)
}

function isNavigable(step: PurchaseStep): boolean {
  return resolvedNavigableSteps.value.includes(step)
}

function isCurrent(step: PurchaseStep): boolean {
  return props.currentStep === step
}

function isFuture(step: PurchaseStep): boolean {
  return step > props.currentStep && !isCompleted(step)
}

function formatStepNumber(step: PurchaseStep): string {
  return String(step).padStart(2, '0')
}

function handleClick(step: PurchaseStep) {
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
          :class="{ 'purchase-steps__connector--completed': isCompleted(steps[index - 1].number) }"
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
              {{ formatStepNumber(step.number) }}
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
  width: 1.5rem;
  height: var(--border-hairline);
  background-color: rgb(var(--outline-variant-rgb) / 0.4);
  flex-shrink: 0;
}

@media (min-width: 60rem) {
  .purchase-steps__connector {
    width: 3rem;
  }
}

.purchase-steps__connector--completed {
  background-color: var(--secondary);
}

/* Step element */
.purchase-steps__step {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  background: none;
  border: none;
  padding: var(--space-xs) 0;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  line-height: 1.4;
  color: var(--on-tertiary-fixed-variant);
  cursor: default;
  white-space: nowrap;
  transition: color var(--duration-standard) var(--ease-standard);
}

.purchase-steps__step--current {
  color: var(--on-surface);
}

.purchase-steps__step--current .purchase-steps__number {
  border-color: var(--secondary);
  color: var(--secondary);
}

.purchase-steps__step--completed {
  color: var(--secondary);
}

.purchase-steps__step--completed .purchase-steps__number {
  background-color: var(--secondary);
  color: var(--surface);
  border-color: var(--secondary);
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
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
  font-family: var(--font-display);
  font-size: 0.75rem;
  letter-spacing: 0;
  flex-shrink: 0;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
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
