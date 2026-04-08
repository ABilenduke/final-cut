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

const isTaken = computed(() => props.seat.status === 'taken' || props.seat.status === 'held')
const isAccessible = computed(() => props.seat.type === 'accessible')
const isPremium = computed(() => props.seat.type === 'premium')

const seatLabel = computed(() => {
  const status = isTaken.value ? 'unavailable' : props.selected ? 'selected' : 'available'
  const section = props.seat.type.charAt(0).toUpperCase() + props.seat.type.slice(1)
  return `Seat ${props.seat.id}, ${status}. Row ${props.seat.row}, seat ${props.seat.number}. ${section} section. ${formatCurrency(props.seat.price)}`
})

function handleClick() {
  if (isTaken.value) return
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
      'auditorium-seat--accessible': isAccessible && !isTaken,
      'auditorium-seat--premium': isPremium && !isTaken,
    }"
    :tabindex="focused ? 0 : -1"
    :aria-label="seatLabel"
    :aria-selected="selected || undefined"
    :aria-disabled="isTaken || undefined"
    :disabled="isTaken"
    @click="handleClick"
    @keydown="handleKeydown"
  >
    <CvIcon v-if="selected" name="check" size="sm" />
    <CvIcon v-else-if="isAccessible && !isTaken" name="accessible" size="sm" />
  </button>
</template>

<style scoped>
.auditorium-seat {
  display: flex;
  align-items: center;
  justify-content: center;
  width: var(--seat-size, 2.5rem);
  height: var(--seat-size, 2.5rem);
  border: none;
  border-radius: 0.125rem;
  background-color: var(--surface-container-high);
  color: var(--on-surface);
  cursor: pointer;
  padding: 0;
  transition:
    background-color var(--duration-standard) var(--ease-emphasis),
    transform var(--duration-standard) var(--ease-emphasis);
}

.auditorium-seat:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: -0.125rem;
}

/* Selected */
.auditorium-seat--selected {
  background-color: var(--primary-container);
  color: var(--primary);
  animation: seat-select var(--duration-standard) var(--ease-emphasis);
}

@keyframes seat-select {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

@media (prefers-reduced-motion: reduce) {
  .auditorium-seat--selected {
    animation: none;
  }
}

/* Taken */
.auditorium-seat--taken {
  background-color: var(--surface-container-low);
  opacity: 0.4;
  cursor: not-allowed;
  pointer-events: none;
}

/* Accessible */
.auditorium-seat--accessible {
  color: var(--secondary);
}

/* Premium */
.auditorium-seat--premium {
  border-bottom: 0.125rem solid var(--secondary-container);
}
</style>
