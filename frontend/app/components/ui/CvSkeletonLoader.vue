<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'text' | 'card' | 'image' | 'circle'
  width?: string
  height?: string
  lines?: number
}>(), {
  variant: 'text',
  width: '100%',
  height: '1rem',
  lines: 1,
})
</script>

<template>
  <div
    class="cv-skeleton"
    role="status"
    aria-busy="true"
  >
    <span class="sr-only">Loading</span>

    <!-- Text variant: multiple lines -->
    <template v-if="variant === 'text'">
      <div
        v-for="i in lines"
        :key="i"
        class="skeleton cv-skeleton__line"
        :style="{
          width: i === lines && lines > 1 ? '75%' : width,
          height: height,
        }"
      />
    </template>

    <!-- Card variant: 16:9 aspect ratio -->
    <div
      v-else-if="variant === 'card'"
      class="skeleton cv-skeleton__card"
      :style="{ width }"
    />

    <!-- Image variant: fills container -->
    <div
      v-else-if="variant === 'image'"
      class="skeleton cv-skeleton__image"
      :style="{ width, height }"
    />

    <!-- Circle variant -->
    <div
      v-else-if="variant === 'circle'"
      class="skeleton cv-skeleton__circle"
      :style="{ width: height, height }"
    />
  </div>
</template>

<style scoped>
.cv-skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.cv-skeleton__line {
  /* Height and width set inline */
}

.cv-skeleton__card {
  aspect-ratio: 16 / 9;
}

.cv-skeleton__image {
  /* Dimensions set inline */
}

.cv-skeleton__circle {
  border-radius: 50%; /* token-exception: circular shape for avatar placeholder */
}
</style>
