<script setup lang="ts">
import type { AccessibilityTag } from '~/types/calendar-event'

const props = withDefaults(defineProps<{
  activeView: 'month' | 'week' | 'list'
  activeFilters: string[]
  activeAccessibilityFilters?: AccessibilityTag[]
}>(), {
  activeAccessibilityFilters: () => [],
})

const emit = defineEmits<{
  'view-change': [view: string]
  'filter-change': [filters: string[]]
  'accessibility-filter-change': [filters: AccessibilityTag[]]
}>()

const views = [
  { value: 'month', label: 'Month' },
  { value: 'week', label: 'Week' },
  { value: 'list', label: 'List' },
] as const

const eventTypes = [
  { value: 'showtime', label: 'Showtimes' },
  { value: 'special_event', label: 'Special Events' },
  { value: 'loyalty_exclusive', label: 'Loyalty Exclusives' },
] as const

const accessibilityOptions: { value: AccessibilityTag; label: string }[] = [
  { value: 'sensory_friendly', label: 'Sensory Friendly' },
  { value: 'open_caption', label: 'Open Captions' },
  { value: 'audio_described', label: 'Audio Described' },
]

function toggleFilter(value: string) {
  const current = [...props.activeFilters]
  const index = current.indexOf(value)
  if (index >= 0) {
    current.splice(index, 1)
  } else {
    current.push(value)
  }
  emit('filter-change', current)
}

function toggleAccessibilityFilter(value: AccessibilityTag) {
  const current = [...props.activeAccessibilityFilters]
  const index = current.indexOf(value)
  if (index >= 0) {
    current.splice(index, 1)
  } else {
    current.push(value)
  }
  emit('accessibility-filter-change', current)
}
</script>

<template>
  <div class="calendar-filters">
    <div class="calendar-filters__section">
      <span class="calendar-filters__heading label-lg">View</span>
      <div class="calendar-filters__view-toggle" role="radiogroup" aria-label="Calendar view">
        <button
          v-for="view in views"
          :key="view.value"
          role="radio"
          :aria-checked="activeView === view.value"
          class="calendar-filters__view-btn label-md"
          :class="{ 'calendar-filters__view-btn--active': activeView === view.value }"
          @click="emit('view-change', view.value)"
        >
          {{ view.label }}
        </button>
      </div>
    </div>

    <div class="calendar-filters__section">
      <span class="calendar-filters__heading label-lg">Event Types</span>
      <div class="calendar-filters__checkboxes">
        <CvCheckbox
          v-for="type in eventTypes"
          :key="type.value"
          :model-value="activeFilters.includes(type.value)"
          :label="type.label"
          @update:model-value="toggleFilter(type.value)"
        />
      </div>
    </div>

    <div class="calendar-filters__section">
      <span class="calendar-filters__heading label-lg">Accessibility</span>
      <div class="calendar-filters__checkboxes">
        <CvCheckbox
          v-for="opt in accessibilityOptions"
          :key="opt.value"
          :model-value="activeAccessibilityFilters.includes(opt.value)"
          :label="opt.label"
          @update:model-value="toggleAccessibilityFilter(opt.value)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.calendar-filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xl);
}

.calendar-filters__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.calendar-filters__heading {
  color: var(--tertiary);
}

.calendar-filters__view-toggle {
  display: flex;
  gap: 0;
}

.calendar-filters__view-btn {
  padding: var(--space-xs) var(--space-md);
  background-color: var(--surface-container-high);
  color: var(--on-surface);
  border: none;
  cursor: pointer;
  transition:
    background-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard);
}

.calendar-filters__view-btn:first-child {
  border-radius: 0.125rem 0 0 0.125rem;
}

.calendar-filters__view-btn:last-child {
  border-radius: 0 0.125rem 0.125rem 0;
}

.calendar-filters__view-btn--active {
  background-color: var(--primary-container);
  color: var(--primary);
}

.calendar-filters__view-btn:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
  z-index: 1;
}

.calendar-filters__checkboxes {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

@media (max-width: 60rem) {
  .calendar-filters {
    flex-direction: column;
    gap: var(--space-lg);
  }
}
</style>
