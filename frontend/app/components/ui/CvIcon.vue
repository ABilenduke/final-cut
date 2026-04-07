<script setup lang="ts">
import { iconPaths, type IconName } from './icons'

const props = withDefaults(defineProps<{
  name: IconName
  size?: 'sm' | 'md' | 'lg' | 'xl'
  label?: string
}>(), {
  size: 'md',
})

const sizeMap: Record<string, string> = {
  sm: 'var(--icon-sm)',
  md: 'var(--icon-md)',
  lg: 'var(--icon-lg)',
  xl: 'var(--icon-xl)',
}

const pathData = computed(() => iconPaths[props.name])
const dimension = computed(() => sizeMap[props.size])
</script>

<template>
  <svg
    v-if="pathData"
    class="cv-icon"
    :class="`cv-icon--${size}`"
    :width="dimension"
    :height="dimension"
    viewBox="0 0 24 24"
    fill="currentColor"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
  >
    <path :d="pathData" />
  </svg>
</template>

<style scoped>
.cv-icon {
  display: inline-block;
  vertical-align: middle;
  flex-shrink: 0;
}
</style>
