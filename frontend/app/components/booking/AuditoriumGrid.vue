<script setup lang="ts">
import type { Auditorium, Seat } from '~/types/auditorium'

const MAX_SEATS = 10

const props = defineProps<{
  auditorium: Auditorium
  seats: Seat[]
  selectedSeatIds: string[]
}>()

const emit = defineEmits<{
  'seat-toggled': [payload: { seatId: string; selected: boolean }]
}>()

const focusedSeatId = ref<string | null>(null)
const gridRef = ref<HTMLElement | null>(null)
const liveRegionRef = ref<HTMLElement | null>(null)

// Group seats by row
const seatsByRow = computed(() => {
  const map = new Map<string, Seat[]>()
  for (const seat of props.seats) {
    if (!map.has(seat.row)) {
      map.set(seat.row, [])
    }
    map.get(seat.row)!.push(seat)
  }
  for (const row of map.values()) {
    row.sort((a, b) => a.number - b.number)
  }
  return map
})

const sortedRows = computed(() => [...seatsByRow.value.keys()].sort())

const selectedCount = computed(() => props.selectedSeatIds.length)
const selectedTotal = computed(() => {
  return props.seats
    .filter(s => props.selectedSeatIds.includes(s.id))
    .reduce((sum, s) => sum + s.price, 0)
})

function announce(message: string) {
  if (liveRegionRef.value) {
    liveRegionRef.value.textContent = message
  }
}

function handleSeatToggle(seat: Seat) {
  const isCurrentlySelected = props.selectedSeatIds.includes(seat.id)

  if (!isCurrentlySelected && selectedCount.value >= MAX_SEATS) {
    announce(`Maximum ${MAX_SEATS} seats allowed per transaction.`)
    return
  }

  emit('seat-toggled', { seatId: seat.id, selected: !isCurrentlySelected })

  if (isCurrentlySelected) {
    announce(`Seat ${seat.id} deselected.`)
  } else {
    const newCount = selectedCount.value + 1
    const newTotal = selectedTotal.value + seat.price
    announce(`Seat ${seat.id} selected. ${newCount} seats selected, ${formatCurrency(newTotal)} total.`)
  }
}

function findSeatInRow(row: string, colIndex: number): Seat | null {
  const seats = seatsByRow.value.get(row)
  if (!seats) return null
  return seats[Math.min(colIndex, seats.length - 1)] ?? null
}

function handleGridKeydown(event: KeyboardEvent) {
  const currentSeat = focusedSeatId.value
    ? props.seats.find(s => s.id === focusedSeatId.value)
    : null

  if (!currentSeat) {
    // Focus first seat in grid
    const firstRow = sortedRows.value[0]
    const firstSeats = seatsByRow.value.get(firstRow)
    if (firstSeats?.[0]) {
      focusedSeatId.value = firstSeats[0].id
    }
    return
  }

  const rowSeats = seatsByRow.value.get(currentSeat.row) ?? []
  const colIndex = rowSeats.findIndex(s => s.id === currentSeat.id)
  const rowIndex = sortedRows.value.indexOf(currentSeat.row)

  switch (event.key) {
    case 'ArrowRight': {
      event.preventDefault()
      const nextCol = colIndex < rowSeats.length - 1 ? colIndex + 1 : 0
      focusedSeatId.value = rowSeats[nextCol].id
      break
    }
    case 'ArrowLeft': {
      event.preventDefault()
      const prevCol = colIndex > 0 ? colIndex - 1 : rowSeats.length - 1
      focusedSeatId.value = rowSeats[prevCol].id
      break
    }
    case 'ArrowDown': {
      event.preventDefault()
      const nextRowIdx = rowIndex < sortedRows.value.length - 1 ? rowIndex + 1 : 0
      const nextSeat = findSeatInRow(sortedRows.value[nextRowIdx], colIndex)
      if (nextSeat) focusedSeatId.value = nextSeat.id
      break
    }
    case 'ArrowUp': {
      event.preventDefault()
      const prevRowIdx = rowIndex > 0 ? rowIndex - 1 : sortedRows.value.length - 1
      const prevSeat = findSeatInRow(sortedRows.value[prevRowIdx], colIndex)
      if (prevSeat) focusedSeatId.value = prevSeat.id
      break
    }
    case 'Home': {
      event.preventDefault()
      const firstAvailable = rowSeats.find(s => s.status === 'available')
      if (firstAvailable) focusedSeatId.value = firstAvailable.id
      break
    }
    case 'End': {
      event.preventDefault()
      const lastAvailable = [...rowSeats].reverse().find(s => s.status === 'available')
      if (lastAvailable) focusedSeatId.value = lastAvailable.id
      break
    }
    case 'Escape': {
      event.preventDefault()
      // Deselect all — handled by parent via emitted events
      for (const id of props.selectedSeatIds) {
        emit('seat-toggled', { seatId: id, selected: false })
      }
      announce('All seats deselected.')
      // Focus first available seat (grid container isn't focusable)
      const firstAvailableId = findFirstAvailableSeatId()
      if (firstAvailableId) focusedSeatId.value = firstAvailableId
      break
    }
  }
}

// Auto-focus the element when focusedSeatId changes
watch(focusedSeatId, (newId) => {
  if (!newId) return
  nextTick(() => {
    const el = gridRef.value?.querySelector(`[data-seat-id="${newId}"]`) as HTMLElement | null
    el?.focus()
  })
})

function findFirstAvailableSeatId(): string | null {
  for (const row of sortedRows.value) {
    const seats = seatsByRow.value.get(row) ?? []
    const seat = seats.find(s => s.status === 'available')
    if (seat) return seat.id
  }
  return null
}

// Initialize focus on first available seat
onMounted(() => {
  const firstAvailableId = findFirstAvailableSeatId()
  if (firstAvailableId) {
    focusedSeatId.value = firstAvailableId
  }
})
</script>

<template>
  <div
    class="auditorium-grid-wrapper"
    :style="{ '--seats-per-row': auditorium.seatsPerRow }"
  >
    <AuditoriumScreenBar />

    <div
      ref="gridRef"
      class="auditorium"
      role="grid"
      :aria-label="`Theater seating chart, ${auditorium.name}`"
      @keydown="handleGridKeydown"
    >
      <div
        v-for="row in sortedRows"
        :key="row"
        class="auditorium__row"
        role="row"
        :aria-label="`Row ${row}`"
      >
        <div class="auditorium__label" aria-hidden="true">{{ row }}</div>
        <div class="auditorium__seats-row">
          <AuditoriumSeat
            v-for="seat in seatsByRow.get(row)"
            :key="seat.id"
            :seat="seat"
            :selected="selectedSeatIds.includes(seat.id)"
            :focused="focusedSeatId === seat.id"
            :data-seat-id="seat.id"
            role="gridcell"
            @toggle="handleSeatToggle(seat)"
          />
        </div>
      </div>
    </div>

    <div
      v-if="selectedCount >= MAX_SEATS"
      class="auditorium-grid-wrapper__limit"
    >
      Maximum {{ MAX_SEATS }} seats per transaction
    </div>

    <!-- Screen reader live region -->
    <div
      ref="liveRegionRef"
      class="sr-only"
      aria-live="polite"
      aria-atomic="true"
    />
  </div>
</template>

<style scoped>
.auditorium-grid-wrapper {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.auditorium__row {
  display: flex;
  gap: 0;
}

.auditorium__label {
  display: flex;
  align-items: center;
  justify-content: center;
  width: var(--space-xl);
  flex-shrink: 0;
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
  position: sticky;
  left: 0;
  z-index: var(--z-card);
  background-color: var(--surface);
}

.auditorium__seats-row {
  display: grid;
  grid-template-columns: repeat(var(--seats-per-row, 10), var(--seat-size, 2.5rem));
  gap: var(--space-xs);
}

@media (max-width: 59.999rem) {
  .auditorium-grid-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x proximity;
  }
}

.auditorium-grid-wrapper__limit {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--primary);
  text-align: center;
}
</style>
