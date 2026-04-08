<script setup lang="ts">
const props = defineProps<{
  rating?: number | null
}>()

const formattedRating = computed(() =>
  props.rating != null ? props.rating.toFixed(1) : null,
)
</script>

<template>
  <CvBadge v-if="formattedRating" variant="accent">{{ formattedRating }}</CvBadge>
</template>

<style scoped>
</style>
