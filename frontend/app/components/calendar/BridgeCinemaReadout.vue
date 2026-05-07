<script setup lang="ts">
interface ReadoutStat {
  label: string
  value: string
}

withDefaults(defineProps<{
  stats?: ReadoutStat[]
}>(), {
  stats: () => [
    { label: 'Members tonight', value: '2 perks live' },
    { label: 'Bar opens', value: '17:30' },
    { label: 'Late showing', value: '22:30 · Aud 03' },
    { label: 'Valet', value: 'Open' },
  ],
})
</script>

<template>
  <aside class="bridge-readout" aria-label="Cinema readout">
    <div v-for="stat in stats" :key="stat.label" class="bridge-readout__cell">
      <span class="bridge-readout__label">{{ stat.label }}</span>
      <b class="bridge-readout__value">{{ stat.value }}</b>
    </div>
  </aside>
</template>

<style scoped>
.bridge-readout {
  background-color: var(--surface-container-lowest);
  border-radius: var(--radius-sm);
  padding: var(--space-md);
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-md);
}

.bridge-readout__cell {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.bridge-readout__label {
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.bridge-readout__value {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: var(--type-body-md);
  letter-spacing: -0.01em;
  color: var(--on-surface);
}
</style>
