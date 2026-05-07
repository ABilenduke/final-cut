<script setup lang="ts">
import { computed, resolveComponent } from 'vue'
import type { IconName } from './icons'

const props = withDefaults(defineProps<{
  icon: IconName
  label: string
  size?: 'sm' | 'default' | 'lg'
  disabled?: boolean
  href?: string
}>(), {
  size: 'default',
  disabled: false,
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

defineOptions({ inheritAttrs: false })

const isInteractive = computed(() => !props.disabled)
const tag = computed(() => {
  if (props.href && isInteractive.value) return resolveComponent('NuxtLink')
  return 'button'
})

function handleClick(event: MouseEvent) {
  if (!isInteractive.value) {
    event.preventDefault()
    return
  }
  emit('click', event)
}
</script>

<template>
  <component
    :is="tag"
    v-bind="$attrs"
    :to="href && isInteractive ? href : undefined"
    :type="tag === 'button' ? 'button' : undefined"
    :disabled="tag === 'button' ? (disabled || undefined) : undefined"
    :aria-disabled="disabled || undefined"
    :aria-label="label"
    :tabindex="disabled ? -1 : undefined"
    class="cv-icon-button"
    :class="[`cv-icon-button--${size}`, { 'cv-icon-button--disabled': disabled }]"
    @click="handleClick"
  >
    <CvIcon :name="icon" size="md" />
  </component>
</template>

<style scoped>
.cv-icon-button {
  display: inline-grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-sm);
  background-color: var(--surface-container-low);
  color: var(--tertiary);
  cursor: pointer;
  text-decoration: none;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.cv-icon-button--sm {
  width: 1.75rem;
  height: 1.75rem;
}

.cv-icon-button--lg {
  width: 3rem;
  height: 3rem;
}

.cv-icon-button:hover:not(.cv-icon-button--disabled) {
  background-color: var(--surface-container-high);
  color: var(--secondary);
}

.cv-icon-button--disabled {
  opacity: 0.5; /* token-exception: component-specific opacity */
  cursor: not-allowed;
}

.cv-icon-button:focus-visible {
  outline: var(--border-hairline) solid var(--secondary);
  outline-offset: 0.125rem;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}
</style>
