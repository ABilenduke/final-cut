<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'tertiary'
  size?: 'sm' | 'default' | 'lg'
  disabled?: boolean
  loading?: boolean
  type?: 'button' | 'submit' | 'reset'
  href?: string
}>(), {
  variant: 'primary',
  size: 'default',
  disabled: false,
  loading: false,
  type: 'button',
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

defineOptions({ inheritAttrs: false })

const isInteractive = computed(() => !props.disabled && !props.loading)
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
    :type="tag === 'button' ? type : undefined"
    :disabled="tag === 'button' ? (disabled || loading || undefined) : undefined"
    :aria-disabled="disabled || loading || undefined"
    :aria-busy="loading || undefined"
    :tabindex="disabled ? -1 : undefined"
    class="cv-button"
    :class="[
      `cv-button--${variant}`,
      `cv-button--${size}`,
      { 'cv-button--disabled': disabled, 'cv-button--loading': loading },
    ]"
    @click="handleClick"
  >
    <span v-if="$slots['icon-left']" class="cv-button__icon cv-button__icon--left" aria-hidden="true">
      <slot name="icon-left" />
    </span>
    <span class="cv-button__label">
      <slot />
    </span>
    <span v-if="$slots['icon-right']" class="cv-button__icon cv-button__icon--right" aria-hidden="true">
      <slot name="icon-right" />
    </span>
    <span v-if="loading" class="cv-button__spinner" aria-hidden="true">
      <svg width="var(--icon-md)" height="var(--icon-md)" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z" />
      </svg>
    </span>
  </component>
</template>

<style scoped>
.cv-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  height: 3rem;
  padding-inline: var(--space-md);
  border: none;
  border-radius: 0.125rem; /* token-exception: design system radius */
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  line-height: 1;
  cursor: pointer;
  text-decoration: none;
  position: relative;
  transition:
    background-color var(--duration-micro) var(--ease-standard),
    transform var(--duration-micro) var(--ease-standard);
}

.cv-button:active:not(.cv-button--disabled):not(.cv-button--loading) {
  transform: scale(0.98); /* token-exception: component-specific transform */
}

/* Variant: Primary */
.cv-button--primary {
  background-color: var(--primary-container);
  color: var(--secondary);
}

/* Variant: Secondary */
.cv-button--secondary {
  background-color: var(--surface-container-high);
  color: var(--on-surface);
}

/* Variant: Tertiary */
.cv-button--tertiary {
  background-color: transparent;
  color: var(--secondary);
  padding-inline: var(--space-xs);
}

.cv-button--tertiary::after {
  content: '';
  position: absolute;
  bottom: var(--space-sm);
  left: var(--space-xs);
  right: var(--space-xs);
  height: 0.0625rem; /* token-exception: sub-pixel decorative line */
  background-color: var(--secondary);
  transform: scaleX(0);
  transform-origin: center;
  transition: transform var(--duration-standard) var(--ease-standard);
}

.cv-button--tertiary:hover::after {
  transform: scaleX(1);
}

/* Sizes */
.cv-button--lg {
  height: 3.5rem;
  padding-inline: var(--space-lg);
  font-size: var(--type-body-lg);
}

@media (pointer: fine) {
  .cv-button--sm {
    height: 2.25rem;
    padding-inline: var(--space-sm);
    font-size: var(--type-body-sm);
  }
}

/* States */
.cv-button--disabled {
  cursor: not-allowed;
  opacity: 0.5; /* token-exception: component-specific opacity */
}

.cv-button--loading {
  cursor: wait;
}

.cv-button--loading .cv-button__label {
  visibility: hidden;
}

.cv-button--loading .cv-button__icon {
  visibility: hidden;
}

.cv-button__spinner {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cv-button__spinner svg {
  width: var(--icon-md);
  height: var(--icon-md);
  animation: cv-button-spin 750ms linear infinite;
}

@keyframes cv-button-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.cv-button__icon {
  display: flex;
  align-items: center;
}
</style>
