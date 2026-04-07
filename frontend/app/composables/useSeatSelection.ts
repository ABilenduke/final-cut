import { useToast } from '~/composables/useToast'
import type { Seat } from '~/types/auditorium'

export function useSeatSelection(initialSeats: Seat[]) {
  const seats = ref<Seat[]>([...initialSeats])
  const selectedSeatIds = ref<Set<string>>(new Set())
  const focusedSeatId = ref<string | null>(null)

  const toast = useToast()

  function toggleSeat(seatId: string): void {
    const seat = seats.value.find((s) => s.id === seatId)
    if (!seat || seat.status !== 'available') return

    const next = new Set(selectedSeatIds.value)
    if (next.has(seatId)) {
      next.delete(seatId)
    } else {
      next.add(seatId)
    }
    selectedSeatIds.value = next
  }

  function isAvailable(seatId: string): boolean {
    const seat = seats.value.find((s) => s.id === seatId)
    return seat?.status === 'available'
  }

  function isSelected(seatId: string): boolean {
    return selectedSeatIds.value.has(seatId)
  }

  const selectedSeats = computed<Seat[]>(() =>
    seats.value.filter((s) => selectedSeatIds.value.has(s.id)),
  )

  function buildGrid(): { rows: string[]; grid: Map<string, Seat[]> } {
    const grid = new Map<string, Seat[]>()
    for (const seat of seats.value) {
      if (!grid.has(seat.row)) {
        grid.set(seat.row, [])
      }
      grid.get(seat.row)!.push(seat)
    }
    // Sort seats within each row by number
    for (const row of grid.values()) {
      row.sort((a, b) => a.number - b.number)
    }
    // Sort rows alphabetically
    const rows = [...grid.keys()].sort()
    return { rows, grid }
  }

  function moveFocus(direction: 'up' | 'down' | 'left' | 'right'): void {
    const { rows, grid } = buildGrid()
    if (rows.length === 0) return

    // Find the currently focused seat
    const currentSeat = focusedSeatId.value
      ? seats.value.find((s) => s.id === focusedSeatId.value)
      : null

    if (!currentSeat) {
      // Focus the first seat in the grid
      const firstRow = grid.get(rows[0])!
      focusedSeatId.value = firstRow[0].id
      return
    }

    const rowIndex = rows.indexOf(currentSeat.row)
    const currentRow = grid.get(currentSeat.row)!
    const colIndex = currentRow.findIndex((s) => s.id === currentSeat.id)

    switch (direction) {
      case 'left': {
        const nextCol = colIndex <= 0 ? currentRow.length - 1 : colIndex - 1
        focusedSeatId.value = currentRow[nextCol].id
        break
      }
      case 'right': {
        const nextCol = colIndex >= currentRow.length - 1 ? 0 : colIndex + 1
        focusedSeatId.value = currentRow[nextCol].id
        break
      }
      case 'up': {
        const nextRowIndex = rowIndex <= 0 ? rows.length - 1 : rowIndex - 1
        const nextRow = grid.get(rows[nextRowIndex])!
        const clampedCol = Math.min(colIndex, nextRow.length - 1)
        focusedSeatId.value = nextRow[clampedCol].id
        break
      }
      case 'down': {
        const nextRowIndex = rowIndex >= rows.length - 1 ? 0 : rowIndex + 1
        const nextRow = grid.get(rows[nextRowIndex])!
        const clampedCol = Math.min(colIndex, nextRow.length - 1)
        focusedSeatId.value = nextRow[clampedCol].id
        break
      }
    }
  }

  function updateSeats(newSeats: Seat[]): void {
    seats.value = [...newSeats]

    const next = new Set(selectedSeatIds.value)
    let changed = false

    for (const id of [...next]) {
      const seat = newSeats.find((s) => s.id === id)
      if (!seat || seat.status !== 'available') {
        next.delete(id)
        changed = true
        if (seat) {
          toast.show({
            message: `Seat ${seat.row}${seat.number} is no longer available`,
            type: 'error',
          })
        } else {
          toast.show({
            message: `A selected seat is no longer available`,
            type: 'error',
          })
        }
      }
    }

    if (changed) {
      selectedSeatIds.value = next
    }
  }

  return {
    seats,
    selectedSeatIds,
    focusedSeatId,
    toggleSeat,
    isAvailable,
    isSelected,
    selectedSeats,
    moveFocus,
    updateSeats,
  }
}
