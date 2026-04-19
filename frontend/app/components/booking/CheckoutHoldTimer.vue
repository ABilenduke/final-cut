<script setup lang="ts">
import type { BookingSeat } from '~/types/booking'

const props = defineProps<{
  timeRemaining: number
  auditorium?: string | null
  seats: readonly BookingSeat[]
  orderRef?: string | null
  changeSeatsHref?: string | null
}>()

const emit = defineEmits<{
  release: []
}>()

const formattedTime = computed<string>(() => {
  const mins = Math.floor(Math.max(0, props.timeRemaining) / 60)
  const secs = Math.max(0, props.timeRemaining) % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const rowLabel = computed<string>(() => {
  if (props.seats.length === 0) return '—'
  const first = props.seats[0]
  return first?.seatId?.match(/^([A-Z]+)/)?.[1] ?? '—'
})

const seatNumbers = computed<string>(() => {
  if (props.seats.length === 0) return '—'
  return props.seats
    .map(s => s.seatId.replace(/^[A-Z]+/, ''))
    .join(', ')
})
</script>

<template>
  <!-- The visible timer updates every second; announcing each tick
       through aria-live would drown screen reader users in noise. Keep
       the strip silent for live-region purposes — meaningful threshold
       changes (5-min warning, expiry) are announced via toast, which
       has its own polite region. -->
  <div class="hold-strip" role="status" aria-live="off">
    <span class="hold-strip__dot" aria-hidden="true" />
    <span class="hold-strip__text">
      Seats held for
      <b class="hold-strip__timer">{{ formattedTime }}</b>
    </span>
    <span class="hold-strip__sep" aria-hidden="true">·</span>
    <span class="hold-strip__text">
      <template v-if="auditorium">{{ auditorium }} · </template>
      Row <b>{{ rowLabel }}</b> · Seats <b>{{ seatNumbers }}</b>
    </span>
    <span class="hold-strip__sep" aria-hidden="true">·</span>
    <span v-if="orderRef" class="hold-strip__text">
      Order ref <b>{{ orderRef }}</b>
    </span>

    <div class="hold-strip__right">
      <NuxtLink v-if="changeSeatsHref" :to="changeSeatsHref" class="hold-strip__link">
        ← Change seats
      </NuxtLink>
      <button type="button" class="hold-strip__link" @click="emit('release')">
        Release seats
      </button>
    </div>
  </div>
</template>

<style scoped>
.hold-strip {
  position: fixed;
  top: 4rem;
  left: 0;
  right: 0;
  height: 2.5rem;
  z-index: var(--z-ticker);
  background-color: var(--surface-container-lowest);
  display: flex;
  align-items: center;
  padding: 0 var(--space-md);
  border-bottom: 0.0625rem solid rgba(87, 66, 62, 0.15);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  gap: var(--space-lg);
  overflow-x: auto;
  white-space: nowrap;
}

@media (min-width: 40rem) {
  .hold-strip {
    padding: 0 var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .hold-strip {
    padding: 0 var(--space-2xl);
    overflow-x: visible;
  }
}

.hold-strip__dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
  background-color: var(--secondary);
  animation: hold-blink 1.6s ease-in-out infinite;
  flex-shrink: 0;
}

.hold-strip__text b {
  color: var(--on-surface);
  font-weight: 500;
  font-family: var(--font-display);
  letter-spacing: 0.04em;
  font-variant-numeric: tabular-nums;
  font-size: 0.8125rem;
  text-transform: none;
}

.hold-strip__timer {
  color: var(--secondary) !important;
}

.hold-strip__sep {
  opacity: 0.4;
}

.hold-strip__right {
  margin-left: auto;
  display: flex;
  gap: var(--space-lg);
  align-items: center;
}

.hold-strip__link {
  color: var(--on-tertiary-fixed-variant);
  text-decoration: none;
  font: inherit;
  background: none;
  border: 0;
  padding: 0;
  cursor: pointer;
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

.hold-strip__link:hover,
.hold-strip__link:focus-visible {
  color: var(--secondary);
}

@keyframes hold-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

@media (prefers-reduced-motion: reduce) {
  .hold-strip__dot {
    animation: none;
  }
}
</style>
