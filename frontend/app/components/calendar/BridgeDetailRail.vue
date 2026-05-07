<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'

defineProps<{
  selectedDate: string
  events: CalendarEvent[]
}>()
</script>

<template>
  <aside class="bridge-detail-rail" aria-label="Selected day details">
    <BridgeDetailContent :selected-date="selectedDate" :events="events" />
  </aside>
</template>

<style scoped>
.bridge-detail-rail {
  position: sticky;
  top: 5.5rem;
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

@media (max-width: 80rem) {
  .bridge-detail-rail {
    display: none;
  }
}
</style>
