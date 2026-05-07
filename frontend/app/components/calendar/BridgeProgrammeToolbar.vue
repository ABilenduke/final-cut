<script setup lang="ts">
interface Props {
  monthLabel: string
  year: number
  view: 'month' | 'week' | 'list'
  todayLabel: string
  volumeLabel?: string
}

withDefaults(defineProps<Props>(), {
  volumeLabel: 'Programme',
})

const emit = defineEmits<{
  'view-change': [view: 'month' | 'week' | 'list']
  prev: []
  next: []
  today: []
}>()

const viewOptions = [
  { value: 'month', label: 'Month' },
  { value: 'week', label: 'Week', disabled: true, hint: 'Coming soon' },
  { value: 'list', label: 'List', disabled: true, hint: 'Coming soon' },
]

function onViewChange(value: string) {
  emit('view-change', value as 'month' | 'week' | 'list')
}
</script>

<template>
  <header class="bridge-toolbar">
    <div class="bridge-toolbar__lead">
      <p class="bridge-toolbar__eyebrow">{{ volumeLabel }}</p>
      <h1 class="bridge-toolbar__title">What's <em>On</em>, {{ monthLabel }} {{ year }}</h1>
    </div>
    <div class="bridge-toolbar__controls">
      <CvSegmentedControl
        :model-value="view"
        :options="viewOptions"
        label="Calendar view"
        @update:model-value="onViewChange"
      />
      <CvIconButton
        icon="chevron-left"
        label="Previous month"
        @click="emit('prev')"
      />
      <button
        type="button"
        class="bridge-toolbar__today"
        @click="emit('today')"
      >
        Today · {{ todayLabel }}
      </button>
      <CvIconButton
        icon="chevron-right"
        label="Next month"
        @click="emit('next')"
      />
    </div>
  </header>
</template>

<style scoped>
.bridge-toolbar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-md);
  margin-bottom: var(--space-xl);
  flex-wrap: wrap;
}

.bridge-toolbar__lead {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
}

.bridge-toolbar__eyebrow {
  margin: 0;
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.bridge-toolbar__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.bridge-toolbar__title {
  margin: 0;
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2.75rem, 5vw, 4.5rem);
  letter-spacing: -0.04em;
  line-height: 0.95;
  color: var(--on-surface);
}

.bridge-toolbar__title :deep(em) {
  font-style: italic;
  color: var(--tertiary);
  font-weight: 400;
  font-size: 0.55em;
  letter-spacing: 0.04em;
  margin-left: 0.25em;
}

.bridge-toolbar__controls {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  flex-wrap: wrap;
}

.bridge-toolbar__today {
  height: 2.25rem;
  padding-inline: 0.85rem;
  background: transparent;
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  letter-spacing: 0.18em;
  text-transform: uppercase;
  border: var(--border-hairline) solid rgba(218, 199, 105, 0.3);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background-color var(--duration-standard) var(--ease-standard);
}

.bridge-toolbar__today:hover {
  background-color: rgba(218, 199, 105, 0.08);
}

.bridge-toolbar__today:focus-visible {
  outline: var(--border-hairline) solid var(--secondary);
  outline-offset: 0.125rem;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}
</style>
