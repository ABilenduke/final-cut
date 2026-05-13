<script setup lang="ts">
import type { BookingSeat } from '~/types/booking'
import type { Showtime } from '~/types/showtime'

const props = defineProps<{
  showtime: Showtime | null
  seats: readonly BookingSeat[]
  partySize: number
  timeRemaining: number
}>()

const emit = defineEmits<{
  'remove-seat': [seatId: string]
  'continue': []
}>()

const seatsCount = computed(() => props.seats.length)

const subtotal = computed<number>(() =>
  props.seats.reduce((sum, s) => sum + s.price, 0),
)

const payLabel = computed<string>(() => {
  if (seatsCount.value === 0) return 'Pick seats to continue'
  if (seatsCount.value < props.partySize) {
    const remaining = props.partySize - seatsCount.value
    return `Pick ${remaining} more`
  }
  return 'Continue to payment'
})

const payAmount = computed<string>(() =>
  seatsCount.value === 0 ? '→' : formatCurrency(subtotal.value),
)

const railSeatList = computed<string>(() => {
  if (seatsCount.value === 0) return 'No seats yet'
  return props.seats.map(s => s.label).join(', ')
})

const formattedHold = computed<string>(() => {
  const t = Math.max(0, props.timeRemaining)
  if (t === 0) return '08:00'
  const mins = Math.floor(t / 60)
  const secs = t % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const canContinue = computed(() => seatsCount.value > 0)

const posterStyle = computed(() => {
  // Deterministic gradient based on the movie id so different films get different poster art
  const hash = ((props.showtime?.movieId ?? 0) * 37) % 360
  return {
    background: `radial-gradient(ellipse at 30% 30%, hsl(${hash} 55% 52%), hsl(${(hash + 20) % 360} 50% 24%) 55%, #1f0d08 100%)`,
  }
})
</script>

<template>
  <div class="seat-rail">
    <section class="bay seat-rail__bay">
      <div class="seat-rail__show">
        <div class="seat-rail__poster" :style="posterStyle" aria-hidden="true" />
        <div class="seat-rail__info">
          <span class="seat-rail__show-title">
            {{ showtime?.movieTitle ?? 'Tonight\'s show' }}
          </span>
          <span class="seat-rail__show-meta">
            {{ showtime?.screenName ?? '—' }} · Atmos
          </span>
          <div class="seat-rail__show-row">
            <b>{{ railSeatList }}</b>
          </div>
        </div>
      </div>

      <header class="seat-rail__head">
        <div>
          <div class="seat-rail__num">§ Ω</div>
          <h2 class="seat-rail__title">Your <em>seats.</em></h2>
        </div>
        <span class="seat-rail__count">{{ seatsCount }} of {{ partySize }}</span>
      </header>

      <div class="seat-rail__stubs" aria-live="polite">
        <template v-if="seatsCount === 0">
          <div class="seat-rail__empty">
            <b>No seats selected</b>
            Tap a seat on the map — or ask us to pick for you.
          </div>
        </template>
        <template v-else>
          <SeatStub
            v-for="seat in seats"
            :key="seat.id"
            :seat="seat"
            @remove="(id) => emit('remove-seat', id)"
          />
        </template>
      </div>

      <dl class="seat-rail__totals">
        <div class="seat-rail__line">
          <span>Seats</span>
          <span class="seat-rail__v">{{ formatCurrency(subtotal) }}</span>
        </div>
        <div class="seat-rail__line">
          <span>Booking fee</span>
          <span class="seat-rail__v seat-rail__v--waived">Waived</span>
        </div>
        <div class="seat-rail__grand">
          <span>Ticket total<small>USD</small></span>
          <span class="seat-rail__grand-v">{{ formatCurrency(subtotal) }}</span>
        </div>
      </dl>

      <button
        type="button"
        class="seat-rail__pay"
        :disabled="!canContinue"
        @click="emit('continue')"
      >
        <span>{{ payLabel }}</span>
        <span class="seat-rail__pay-amt">{{ payAmount }}</span>
      </button>

      <p class="seat-rail__note">
        Tickets are issued at payment. Hold expires after
        <b>{{ formattedHold }}</b> of inactivity — seats release automatically.
      </p>
    </section>
  </div>
</template>

<style scoped>
.seat-rail {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.seat-rail__bay {
  padding: var(--space-lg);
}

.seat-rail__show {
  display: flex;
  gap: var(--space-md);
  align-items: flex-start;
  padding-bottom: var(--space-sm);
  margin-bottom: var(--space-sm);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.seat-rail__poster {
  width: 3.25rem;
  aspect-ratio: 2 / 3;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}

.seat-rail__poster::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg,
    transparent 0 2px,
    rgba(0, 0, 0, 0.08) 2px 3px
  );
  mix-blend-mode: multiply;
}

.seat-rail__info {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--tertiary);
  line-height: 1.4;
  min-width: 0;
}

.seat-rail__show-title {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--on-surface);
  letter-spacing: -0.005em;
  display: block;
  margin-bottom: 0.15rem;
  /* Keep long titles readable */
  overflow: hidden;
  text-overflow: ellipsis;
}

.seat-rail__show-meta {
  display: block;
}

.seat-rail__show-row {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: 0.25rem;
  word-break: break-word;
}

.seat-rail__show-row b {
  color: var(--secondary);
  font-family: var(--font-display);
  letter-spacing: 0.04em;
  font-weight: 500;
  text-transform: none;
}

.seat-rail__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-md);
  padding-bottom: var(--space-sm);
  margin-bottom: var(--space-sm);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.seat-rail__num {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
}

.seat-rail__title {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.1;
  margin: 0.2rem 0 0;
  color: var(--on-surface);
}

.seat-rail__title em {
  font-style: italic;
  color: var(--tertiary);
}

.seat-rail__count {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.seat-rail__stubs {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-height: 2rem;
}

.seat-rail__empty {
  padding: var(--space-lg) 0;
  text-align: center;
  font-family: var(--font-body);
  font-style: italic;
  font-size: 0.8125rem;
  color: var(--on-tertiary-fixed-variant);
  border: var(--border-hairline) dashed rgb(var(--outline-variant-rgb) / 0.25);
  border-radius: var(--radius-sm);
}

.seat-rail__empty b {
  display: block;
  font-family: var(--font-display);
  font-style: normal;
  font-size: 1rem;
  color: var(--tertiary);
  margin-bottom: 0.35rem;
  letter-spacing: -0.005em;
}

.seat-rail__totals {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  margin: var(--space-md) 0 0;
  padding-top: var(--space-md);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.seat-rail__line {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--tertiary);
}

.seat-rail__v {
  font-family: var(--font-display);
  color: var(--on-surface);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
}

.seat-rail__v--waived {
  color: var(--secondary);
}

.seat-rail__grand {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-sm);
  margin-top: 0.15rem;
  border-top: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.3);
  font-family: var(--font-body);
  font-size: 1rem;
  color: var(--on-surface);
}

.seat-rail__grand small {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.12em;
  margin-left: 0.4rem;
}

.seat-rail__grand-v {
  font-family: var(--font-display);
  font-size: 1.875rem;
  color: var(--secondary);
  letter-spacing: -0.02em;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.seat-rail__pay {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 3.25rem;
  padding: 0 1.25rem;
  margin-top: var(--space-md);
  border-radius: var(--radius-sm);
  background-color: var(--secondary);
  color: var(--surface);
  border: none;
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  width: 100%;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    transform var(--duration-micro) var(--ease-standard);
}

.seat-rail__pay:hover:not(:disabled) {
  background-color: var(--secondary-hover);
}

.seat-rail__pay:active:not(:disabled) {
  transform: scale(0.99);
}

.seat-rail__pay:disabled {
  background-color: var(--surface-container-high);
  color: var(--on-tertiary-fixed-variant);
  cursor: not-allowed;
}

.seat-rail__pay-amt {
  font-variant-numeric: tabular-nums;
}

.seat-rail__note {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  line-height: 1.55;
  color: var(--on-tertiary-fixed-variant);
  margin: var(--space-md) 0 0;
  padding-top: var(--space-md);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.15);
  letter-spacing: 0.02em;
}

.seat-rail__note b {
  color: var(--secondary);
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}
</style>
