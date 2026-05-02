<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'default' | 'accent' | 'warning'
  size?: 'sm' | 'default'
}>(), {
  variant: 'default',
  size: 'default',
})
</script>

<template>
  <span
    class="cv-badge"
    :class="[`cv-badge--${variant}`, `cv-badge--size-${size}`]"
  >
    <slot />
  </span>
</template>

<style scoped>
.cv-badge {
  display: inline-flex;
  align-items: center;
  padding: var(--space-2xs) var(--space-xs);
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  line-height: 1.4;
  white-space: nowrap;
}

.cv-badge--default {
  background-color: var(--surface-container-high);
  color: var(--tertiary);
}

.cv-badge--accent {
  background-color: var(--primary-container);
  color: var(--primary);
}

.cv-badge--warning {
  background-color: var(--secondary-container);
  color: var(--on-surface);
}

.cv-badge--size-default {
  font-size: var(--type-label-md);
}

.cv-badge--size-sm {
  font-size: var(--type-label-sm);
}
</style>
