<script setup lang="ts">
defineProps<{
  coordinates: { lat: number; lng: number }
  address?: string
}>()
</script>

<template>
  <div class="contact-map" role="img" :aria-label="`Theater location${address ? `: ${address}` : ''}`">
    <div class="contact-map__placeholder">
      <svg
        class="contact-map__icon"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
      >
        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
      </svg>
      <p v-if="address" class="contact-map__address body-md">{{ address }}</p>
      <p class="contact-map__coords label-sm">
        {{ Math.abs(coordinates.lat).toFixed(4) }}°{{ coordinates.lat >= 0 ? 'N' : 'S' }}, {{ Math.abs(coordinates.lng).toFixed(4) }}°{{ coordinates.lng >= 0 ? 'E' : 'W' }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.contact-map {
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
  overflow: hidden;
  min-height: 15rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.contact-map__placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-xl);
  text-align: center;
}

.contact-map__icon {
  width: var(--icon-xl);
  height: var(--icon-xl);
  color: var(--tertiary);
}

.contact-map__address {
  color: var(--on-surface);
  margin: 0;
}

.contact-map__coords {
  color: var(--tertiary);
  margin: 0;
}
</style>
