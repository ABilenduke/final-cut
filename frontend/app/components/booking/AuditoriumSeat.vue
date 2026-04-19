<script setup lang="ts">
import type { Seat } from '~/types/auditorium'

const props = defineProps<{
  seat: Seat
  selected: boolean
  focused: boolean
}>()

const emit = defineEmits<{
  toggle: []
}>()

const isTaken = computed(() => props.seat.status === 'taken')
const isHeld = computed(() => props.seat.status === 'held')
const isUnavailable = computed(() => isTaken.value || isHeld.value)
const isAccessible = computed(() => props.seat.type === 'accessible')
const isPremium = computed(() => props.seat.type === 'premium')

const tierLabel = computed(() => {
  if (props.seat.type === 'premium') return 'Premiere recliner'
  if (props.seat.type === 'accessible') return 'Accessible'
  return 'Standard orchestra'
})

const seatLabel = computed(() => {
  const status = isTaken.value
    ? 'unavailable'
    : isHeld.value
      ? 'on hold'
      : props.selected
        ? 'selected'
        : 'available'
  return `Seat ${props.seat.id}, ${status}. Row ${props.seat.row}, seat ${props.seat.number}. ${tierLabel.value} section. ${formatCurrency(props.seat.price)}`
})

const tooltipStatus = computed(() => {
  if (isTaken.value) return 'Sold'
  if (isHeld.value) return 'On hold'
  return `${formatCurrency(props.seat.price)} · ${tierLabel.value}`
})

function handleClick() {
  if (isUnavailable.value) return
  emit('toggle')
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    handleClick()
  }
}
</script>

<template>
  <button
    class="auditorium-seat"
    :class="{
      'auditorium-seat--selected': selected,
      'auditorium-seat--taken': isTaken,
      'auditorium-seat--held': isHeld,
      'auditorium-seat--accessible': isAccessible && !isUnavailable,
      'auditorium-seat--premiere': isPremium && !isUnavailable,
    }"
    :tabindex="focused ? 0 : -1"
    :aria-label="seatLabel"
    :aria-selected="selected || undefined"
    :aria-disabled="isUnavailable || undefined"
    @click="handleClick"
    @keydown="handleKeydown"
  >
    <span v-if="isAccessible && !isUnavailable" class="auditorium-seat__glyph" aria-hidden="true">♿</span>
    <span v-else class="auditorium-seat__num" aria-hidden="true">{{ seat.number }}</span>

    <span class="auditorium-seat__tip" role="presentation">
      <b>{{ seat.row }} · {{ seat.number }}</b>
      <span>{{ tooltipStatus }}</span>
    </span>
  </button>
</template>

<style scoped>
.auditorium-seat {
  position: relative;
  display: grid;
  place-items: center;
  width: var(--seat-size, 1.75rem);
  height: var(--seat-size, 1.75rem);
  padding: 0;
  border: 0.0625rem solid rgba(87, 66, 62, 0.4);
  border-radius: 0.3rem 0.3rem 0.2rem 0.2rem;
  background-color: var(--surface-container);
  color: var(--on-tertiary-fixed-variant);
  font-family: var(--font-display);
  font-size: 0.625rem;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
  transition:
    transform var(--duration-micro) var(--ease-standard),
    background-color var(--duration-micro) var(--ease-standard),
    border-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard),
    box-shadow var(--duration-standard) var(--ease-emphasis);
}

.auditorium-seat:hover:not(:disabled):not(.auditorium-seat--taken):not(.auditorium-seat--held) {
  transform: translateY(-0.125rem);
  border-color: var(--secondary);
  color: var(--secondary);
  z-index: 2;
}

.auditorium-seat:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
  z-index: 3;
}

.auditorium-seat__glyph {
  font-size: 0.75rem;
  line-height: 1;
}

.auditorium-seat__num {
  line-height: 1;
}

/* Premiere (maroon-tinted, wider-feeling) */
.auditorium-seat--premiere {
  background-color: rgba(85, 0, 0, 0.18);
  border-color: rgba(168, 110, 100, 0.35);
  color: var(--tertiary);
  border-radius: 0.4rem 0.4rem 0.25rem 0.25rem;
}

/* Accessible (deeper surface tier, gold glyph) */
.auditorium-seat--accessible {
  background-color: rgba(42, 42, 42, 0.6);
  border-color: rgba(168, 139, 134, 0.4);
  color: var(--tertiary);
}

/* Selected — highest priority */
.auditorium-seat--selected,
.auditorium-seat--selected:hover {
  background-color: var(--secondary) !important;
  color: var(--surface) !important;
  border-color: var(--secondary) !important;
  transform: translateY(-0.125rem);
  box-shadow: 0 0.25rem 0.75rem rgba(218, 199, 105, 0.35);
  animation: seat-select var(--duration-standard) var(--ease-emphasis);
}

@keyframes seat-select {
  0% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-0.125rem) scale(1.05); }
  100% { transform: translateY(-0.125rem) scale(1); }
}

/* Taken / sold — with decorative X overlay */
.auditorium-seat--taken {
  background-color: var(--surface-container-lowest);
  border-color: rgba(87, 66, 62, 0.2);
  color: rgba(138, 128, 116, 0.3);
  cursor: not-allowed;
}

.auditorium-seat--taken::before {
  content: '';
  position: absolute;
  inset: 25%;
  background: linear-gradient(
    45deg,
    transparent 45%,
    rgba(138, 128, 116, 0.28) 45% 55%,
    transparent 55%
  );
  pointer-events: none;
}

/* Held (dashed, warm tint) */
.auditorium-seat--held {
  background-color: rgba(255, 180, 120, 0.08);
  border-color: rgba(196, 112, 64, 0.35);
  border-style: dashed;
  color: var(--on-tertiary-fixed-variant);
  cursor: not-allowed;
}

/* Tooltip */
.auditorium-seat__tip {
  position: absolute;
  bottom: calc(100% + 0.5rem);
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  background-color: var(--surface-container-lowest);
  border: 0.0625rem solid rgba(218, 199, 105, 0.3);
  padding: 0.35rem 0.6rem;
  border-radius: 0.125rem;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  line-height: 1.3;
  color: var(--on-surface);
  letter-spacing: 0;
  text-transform: none;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transition: opacity var(--duration-micro) var(--ease-standard);
  z-index: 10;
}

.auditorium-seat__tip b {
  font-family: var(--font-display);
  font-weight: 500;
  color: var(--secondary);
  letter-spacing: 0.04em;
}

.auditorium-seat:hover .auditorium-seat__tip,
.auditorium-seat:focus-visible .auditorium-seat__tip {
  opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
  .auditorium-seat,
  .auditorium-seat--selected,
  .auditorium-seat:hover {
    transition: none;
    animation: none;
    transform: none;
  }
}
</style>
