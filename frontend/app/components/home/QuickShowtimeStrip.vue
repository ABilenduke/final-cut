<script setup lang="ts">
import type { Location } from '~/types/location'

const props = defineProps<{
  location: Location | null
}>()

const emit = defineEmits<{
  'change-location': []
  'track': [event: { action: string; date: string }]
}>()

type DateOption = 'today' | 'tomorrow' | 'weekend'
const activeDate = ref<DateOption>('today')

const dateOptions: Array<{ value: DateOption; label: string }> = [
  { value: 'today', label: 'Today' },
  { value: 'tomorrow', label: 'Tomorrow' },
  { value: 'weekend', label: 'This Weekend' },
]
</script>

<template>
  <div class="showtime-strip">
    <div class="showtime-strip__content">
      <!-- No location state -->
      <template v-if="!location">
        <span class="showtime-strip__prompt body-sm">Select a location to see showtimes</span>
        <CvButton
          variant="secondary"
          size="sm"
          @click="emit('change-location')"
        >
          Choose Location
        </CvButton>
      </template>

      <!-- Has location -->
      <template v-else>
        <!-- Location display -->
        <div class="showtime-strip__location">
          <span class="showtime-strip__location-name body-sm">{{ location.name }}</span>
          <button
            class="showtime-strip__change button-reset body-sm"
            @click="emit('change-location')"
          >
            Change
          </button>
        </div>

        <!-- Date pills -->
        <div class="showtime-strip__dates" role="group" aria-label="Select date">
          <button
            v-for="opt in dateOptions"
            :key="opt.value"
            class="showtime-strip__pill label-lg"
            :class="{ 'showtime-strip__pill--active': activeDate === opt.value }"
            @click="activeDate = opt.value"
            :aria-pressed="activeDate === opt.value"
          >
            {{ opt.label }}
          </button>
        </div>

        <!-- CTA -->
        <CvButton
          variant="primary"
          size="sm"
          href="/movies?status=now_showing"
          @click="emit('track', { action: 'browse-showtimes', date: activeDate })"
        >
          Browse Showtimes
        </CvButton>
      </template>
    </div>
  </div>
</template>

<style scoped>
.showtime-strip {
  background-color: var(--surface-container);
  padding: var(--space-md) 0;
}

.showtime-strip__content {
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-2xl);
  display: flex;
  align-items: center;
  gap: var(--space-md);
  flex-wrap: wrap;
}

.showtime-strip__prompt {
  color: var(--tertiary);
}

.showtime-strip__location {
  display: flex;
  align-items: center;
  gap: var(--space-xs);
}

.showtime-strip__location-name {
  color: var(--on-surface);
}

.showtime-strip__change {
  color: var(--secondary);
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
}

.showtime-strip__change:hover {
  text-decoration: underline;
}

.showtime-strip__dates {
  display: flex;
  gap: var(--space-xs);
}

.showtime-strip__pill {
  background: transparent;
  border: none;
  color: var(--tertiary);
  cursor: pointer;
  padding: var(--space-xs) var(--space-sm);
  border-radius: 0.125rem;
  font-family: var(--font-body);
  font-size: var(--type-label-lg);
  transition: background-color var(--duration-micro) var(--ease-standard),
              color var(--duration-micro) var(--ease-standard);
}

.showtime-strip__pill:hover {
  background-color: var(--surface-container-high);
}

.showtime-strip__pill--active {
  background-color: var(--primary-container);
  color: var(--primary);
}

/* Responsive: wrap to two rows on mobile */
@media (max-width: 60rem) {
  .showtime-strip__content {
    padding-inline: var(--space-md);
  }
}
</style>
