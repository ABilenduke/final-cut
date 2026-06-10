<script setup lang="ts">
import { useApiFetch } from '~/utils/api'

interface ReadoutStat {
  label: string
  value: string
}

interface CinemaReadoutResponse {
  data: {
    screeningsToday: number
    doorsOpen: string | null
    lateShowing: { time: string; auditorium: string } | null
    seatsLeftTonight: number | null
  }
}

const props = defineProps<{
  /** Explicit stats override; otherwise the live readout API drives the panel. */
  stats?: ReadoutStat[]
}>()

// Live telemetry (admin-v4 Plan 04) — replaces the v1 static stub. The
// endpoint derives today's slate, doors-open, late showing, and seats left
// from real data; the static values remain only as an unreachable-API
// fallback so the panel never renders empty.
const FALLBACK_STATS: ReadoutStat[] = [
  { label: 'Members tonight', value: '2 perks live' },
  { label: 'Bar opens', value: '17:30' },
  { label: 'Late showing', value: '22:30 · Aud 03' },
  { label: 'Valet', value: 'Open' },
]

const { data: readoutData } = useApiFetch<CinemaReadoutResponse>('/api/cinema-readout', {
  key: 'cinema-readout',
})

const displayStats = computed<ReadoutStat[]>(() => {
  if (props.stats) return props.stats

  const readout = readoutData.value?.data
  if (!readout) return FALLBACK_STATS

  const stats: ReadoutStat[] = [
    { label: 'Screenings today', value: String(readout.screeningsToday) },
  ]
  if (readout.doorsOpen) stats.push({ label: 'Doors open', value: readout.doorsOpen })
  if (readout.lateShowing) {
    stats.push({
      label: 'Late showing',
      value: `${readout.lateShowing.time} · ${readout.lateShowing.auditorium}`,
    })
  }
  if (readout.seatsLeftTonight !== null) {
    stats.push({ label: 'Seats left tonight', value: String(readout.seatsLeftTonight) })
  }
  return stats
})
</script>

<template>
  <aside class="bridge-readout" aria-label="Cinema readout">
    <div v-for="stat in displayStats" :key="stat.label" class="bridge-readout__cell">
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
