<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'low' | 'default' | 'high'
  interactive?: boolean
  href?: string
}>(), {
  variant: 'default',
  interactive: false,
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

const tag = computed(() => {
  if (props.href) return resolveComponent('NuxtLink')
  if (props.interactive) return 'div'
  return 'div'
})

const interactiveAttrs = computed(() => {
  if (props.href) return {}
  if (props.interactive) return { tabindex: 0, role: 'button' as const }
  return {}
})

function handleClick(event: MouseEvent) {
  if (props.interactive || props.href) {
    emit('click', event)
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (props.interactive && !props.href && (event.key === 'Enter' || event.key === ' ')) {
    event.preventDefault()
    emit('click', event as unknown as MouseEvent)
  }
}
</script>

<template>
  <component
    :is="tag"
    :to="href || undefined"
    v-bind="interactiveAttrs"
    class="cv-card"
    :class="[
      `cv-card--${variant}`,
      { 'cv-card--interactive': interactive || href },
    ]"
    @click="handleClick"
    @keydown="handleKeydown"
  >
    <div v-if="$slots.header" class="cv-card__header">
      <slot name="header" />
    </div>
    <div class="cv-card__body">
      <slot />
    </div>
    <div v-if="$slots.footer" class="cv-card__footer">
      <slot name="footer" />
    </div>
  </component>
</template>

<style scoped>
.cv-card {
  display: flex;
  flex-direction: column;
  padding: var(--space-md);
  border-radius: 0.25rem; /* token-exception: component spec overrides 0.125rem */
  outline: 0.0625rem solid color-mix(in srgb, var(--outline-variant) 15%, transparent); /* token-exception: sub-pixel edge catch */
  text-decoration: none;
  color: inherit;
  transition: transform var(--duration-standard) var(--ease-standard);
}

.cv-card--low {
  background-color: var(--surface-container-low);
}

.cv-card--default {
  background-color: var(--surface-container);
}

.cv-card--high {
  background-color: var(--surface-container-high);
}

.cv-card--interactive {
  cursor: pointer;
}

.cv-card--interactive:hover {
  transform: translateY(-0.125rem); /* token-exception: component-specific transform */
}

.cv-card__header {
  margin-bottom: var(--space-sm);
}

.cv-card__footer {
  margin-top: var(--space-sm);
}
</style>
