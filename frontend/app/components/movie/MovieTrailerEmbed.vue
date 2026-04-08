<script setup lang="ts">
defineProps<{
  trailerKey: string
  title: string
}>()
</script>

<template>
  <div class="trailer-embed">
    <div class="trailer-embed__container aspect-video">
      <iframe
        :src="`https://www.youtube.com/embed/${trailerKey}`"
        :title="`${title} trailer`"
        loading="lazy"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
        class="trailer-embed__iframe"
      />
    </div>
  </div>
</template>

<style scoped>
.trailer-embed__container {
  background-color: var(--surface-container-lowest);
  border-radius: 0.125rem;
  overflow: hidden;
}

.trailer-embed__iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}
</style>
