<script setup lang="ts">
import type { BookingSeat } from '~/types/booking'

const props = defineProps<{
  seat: BookingSeat
}>()

const emit = defineEmits<{
  remove: [seatId: string]
}>()

const row = computed(() => props.seat.seatId.match(/^([A-Z]+)/)?.[1] ?? '—')
const num = computed(() => props.seat.seatId.replace(/^[A-Z]+/, ''))

const tierLabel = computed(() => {
  const section = (props.seat.section ?? '').toLowerCase()
  if (section === 'premium' || section === 'premiere') return 'Premiere recliner'
  if (section === 'accessible') return 'Accessible'
  if (section === 'companion') return 'Companion pair'
  return 'Standard orchestra'
})
</script>

<template>
  <div class="seat-stub">
    <span class="seat-stub__tag">{{ row }} · {{ num }}</span>
    <div class="seat-stub__lbl">
      Seat {{ seat.seatId }}
      <span class="seat-stub__tier">{{ tierLabel }} · Adult</span>
    </div>
    <div class="seat-stub__right">
      <span class="seat-stub__pr">{{ formatCurrency(seat.price) }}</span>
      <button
        type="button"
        class="seat-stub__rm"
        @click="emit('remove', seat.seatId)"
      >Remove</button>
    </div>
  </div>
</template>

<style scoped>
.seat-stub {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-sm);
  align-items: center;
  padding: 0.55rem 0.75rem;
  background-color: var(--surface-container-low);
  border: 0.0625rem dashed rgba(87, 66, 62, 0.35);
  border-radius: 0.125rem;
  position: relative;
}

.seat-stub::before,
.seat-stub::after {
  content: '';
  position: absolute;
  top: 50%;
  width: 0.6rem;
  height: 0.6rem;
  border-radius: 50%;
  background-color: var(--surface-container);
  transform: translateY(-50%);
}

.seat-stub::before { left: -0.3rem; }
.seat-stub::after { right: -0.3rem; }

.seat-stub__tag {
  font-family: var(--font-display);
  font-size: 0.6875rem;
  color: var(--secondary);
  background-color: rgba(218, 199, 105, 0.08);
  padding: 0.2rem 0.4rem;
  border-radius: 0.125rem;
  letter-spacing: 0.04em;
  font-variant-numeric: tabular-nums;
}

.seat-stub__lbl {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--on-surface);
  letter-spacing: -0.005em;
  min-width: 0;
}

.seat-stub__tier {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: block;
  margin-top: 0.1rem;
}

.seat-stub__right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.15rem;
}

.seat-stub__pr {
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
  color: var(--tertiary);
  font-size: 0.875rem;
}

.seat-stub__rm {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  background: none;
  border: 0;
  padding: 0;
  cursor: pointer;
  transition: color var(--duration-micro) var(--ease-standard);
}

.seat-stub__rm:hover,
.seat-stub__rm:focus-visible {
  color: var(--secondary);
}
</style>
