<script setup lang="ts">
import {
  ALL_CHIPS,
  FC_TYPE_COLORS,
  type ChipSlug,
  useBridgeFilters,
} from '~/composables/useBridgeFilters'

const emit = defineEmits<{
  change: []
}>()

const { isActive, toggleChip } = useBridgeFilters()

const CHIP_LABELS: Record<ChipSlug, string> = {
  showtime: 'Showtimes',
  special: 'Specials',
  member: 'Members',
  sensory: 'Sensory',
  captions: 'Captions',
  audio: 'Audio Described',
}

const chips = ALL_CHIPS.map((slug) => ({ slug, label: CHIP_LABELS[slug] }))

function onToggle(slug: ChipSlug) {
  toggleChip(slug)
  emit('change')
}

const legend = [
  { color: FC_TYPE_COLORS.special, label: 'Special' },
  { color: FC_TYPE_COLORS.member, label: 'Members' },
  { color: FC_TYPE_COLORS.sensory, label: 'Sensory' },
]
</script>

<template>
  <div class="bridge-filter-ribbon" role="group" aria-label="Programme filters">
    <div class="bridge-filter-ribbon__chips">
      <CvChip
        v-for="chip in chips"
        :key="chip.slug"
        :label="chip.label"
        :color="FC_TYPE_COLORS[chip.slug]"
        :active="isActive(chip.slug)"
        compact
        @toggle="onToggle(chip.slug)"
      />
    </div>
    <div class="bridge-filter-ribbon__spacer" />
    <ul class="bridge-filter-ribbon__legend" aria-label="Type legend">
      <li v-for="item in legend" :key="item.label" class="bridge-filter-ribbon__legend-item">
        <span class="bridge-filter-ribbon__legend-dot" :style="{ '--bridge-legend-color': item.color }" />
        {{ item.label }}
      </li>
    </ul>
  </div>
</template>

<style scoped>
.bridge-filter-ribbon {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-md) 0;
  margin-bottom: var(--space-md);
  flex-wrap: wrap;
  border-bottom: var(--border-hairline) solid rgba(87, 66, 62, 0.15);
}

.bridge-filter-ribbon__chips {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: center;
}

.bridge-filter-ribbon__spacer {
  flex: 1;
  min-width: 0.5rem;
}

.bridge-filter-ribbon__legend {
  display: flex;
  gap: var(--space-md);
  flex-wrap: wrap;
  font-size: 0.6875rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin: 0;
  padding: 0;
  list-style: none;
}

.bridge-filter-ribbon__legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.bridge-filter-ribbon__legend-dot {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 50%;
  background-color: var(--bridge-legend-color, var(--tertiary));
}
</style>
