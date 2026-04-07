<script setup lang="ts">
const props = defineProps<{
  rating: number
}>()

const formattedRating = computed(() => props.rating.toFixed(1))
</script>

<template>
  <CvBadge variant="accent">{{ formattedRating }}</CvBadge>
</template>

<style scoped>
</style>
