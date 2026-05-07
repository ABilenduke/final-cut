<script setup lang="ts">
const props = withDefaults(defineProps<{
  label: string
  active?: boolean
  color?: string
  compact?: boolean
  disabled?: boolean
}>(), {
  active: false,
  compact: false,
  disabled: false,
})

const emit = defineEmits<{
  'update:active': [value: boolean]
  toggle: []
}>()

function handleClick() {
  if (props.disabled) return
  emit('toggle')
  emit('update:active', !props.active)
}
</script>

<template>
  <button
    type="button"
    class="cv-chip"
    :class="{
      'cv-chip--active': active,
      'cv-chip--compact': compact,
      'cv-chip--disabled': disabled,
    }"
    :style="color ? { '--cv-chip-color': color } : undefined"
    :aria-pressed="active"
    :aria-disabled="disabled || undefined"
    :disabled="disabled || undefined"
    @click="handleClick"
  >
    <span v-if="color" class="cv-chip__dot" aria-hidden="true" />
    <span class="cv-chip__label">{{ label }}</span>
  </button>
</template>

<style scoped>
.cv-chip {
  --cv-chip-color: var(--tertiary);
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 0.4rem 0.75rem;
  background: transparent;
  border: var(--border-hairline) solid rgba(87, 66, 62, 0.4);
  border-radius: var(--radius-sm);
  color: var(--on-tertiary-fixed-variant);
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  letter-spacing: 0.08em;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.cv-chip__dot {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 50%;
  background-color: var(--cv-chip-color);
  opacity: 0.5;
  transition:
    opacity var(--duration-standard) var(--ease-standard),
    box-shadow var(--duration-standard) var(--ease-standard);
}

.cv-chip:hover:not(.cv-chip--disabled) {
  border-color: var(--outline);
  color: var(--on-surface);
}

.cv-chip--active {
  background-color: rgba(218, 199, 105, 0.06);
  border-color: rgba(218, 199, 105, 0.5);
  color: var(--on-surface);
}

.cv-chip--active .cv-chip__dot {
  opacity: 1;
  box-shadow: 0 0 0.375rem var(--cv-chip-color);
}

.cv-chip--compact {
  padding: 0.3rem 0.55rem;
  font-size: var(--type-label-sm);
}

.cv-chip--disabled {
  opacity: 0.5; /* token-exception: component-specific opacity */
  cursor: not-allowed;
}

.cv-chip:focus-visible {
  outline: var(--border-hairline) solid var(--secondary);
  outline-offset: 0.125rem;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}
</style>
