import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Seat } from '~/types/auditorium'

const mockToastShow = vi.fn()
vi.mock('~/composables/useToast', () => ({
  useToast: () => ({ show: mockToastShow, dismiss: vi.fn(), toasts: { value: [] } }),
}))

function makeSeat(
  id: string,
  row: string,
  number: number,
  status: Seat['status'] = 'available',
  type: Seat['type'] = 'standard',
): Seat {
  return { id, row, number, status, type, price: 1200 }
}

const SEATS: Seat[] = [
  makeSeat('A1', 'A', 1),
  makeSeat('A2', 'A', 2),
  makeSeat('A3', 'A', 3),
  makeSeat('B1', 'B', 1),
  makeSeat('B2', 'B', 2, 'taken'),
  makeSeat('B3', 'B', 3),
]

describe('useSeatSelection', () => {
  beforeEach(() => {
    mockToastShow.mockClear()
  })

  // Lazy import to ensure mocks are registered before module loads
  async function create(seats: Seat[] = SEATS) {
    const { useSeatSelection } = await import('~/composables/useSeatSelection')
    return useSeatSelection(seats)
  }

  it('toggleSeat adds to selection', async () => {
    const { toggleSeat, isSelected } = await create()
    toggleSeat('A1')
    expect(isSelected('A1')).toBe(true)
  })

  it('toggleSeat removes from selection (toggle off)', async () => {
    const { toggleSeat, isSelected } = await create()
    toggleSeat('A1')
    expect(isSelected('A1')).toBe(true)
    toggleSeat('A1')
    expect(isSelected('A1')).toBe(false)
  })

  it('toggleSeat ignores taken seats', async () => {
    const { toggleSeat, isSelected } = await create()
    toggleSeat('B2')
    expect(isSelected('B2')).toBe(false)
  })

  it('toggleSeat ignores held seats', async () => {
    const seats = [makeSeat('H1', 'H', 1, 'held')]
    const { toggleSeat, isSelected } = await create(seats)
    toggleSeat('H1')
    expect(isSelected('H1')).toBe(false)
  })

  it('isAvailable returns false for taken seats', async () => {
    const { isAvailable } = await create()
    expect(isAvailable('A1')).toBe(true)
    expect(isAvailable('B2')).toBe(false)
  })

  it('selectedSeats computed reflects selections', async () => {
    const { toggleSeat, selectedSeats } = await create()
    expect(selectedSeats.value).toHaveLength(0)
    toggleSeat('A1')
    toggleSeat('A3')
    expect(selectedSeats.value).toHaveLength(2)
    expect(selectedSeats.value.map((s) => s.id).sort()).toEqual(['A1', 'A3'])
  })

  it('updateSeats merges new availability', async () => {
    const { seats, updateSeats } = await create()
    const updated = SEATS.map((s) =>
      s.id === 'B2' ? { ...s, status: 'available' as const } : s,
    )
    updateSeats(updated)
    const b2 = seats.value.find((s) => s.id === 'B2')
    expect(b2?.status).toBe('available')
  })

  it('updateSeats deselects taken seats and fires toast', async () => {
    const { toggleSeat, isSelected, updateSeats } = await create()
    toggleSeat('A1')
    expect(isSelected('A1')).toBe(true)

    const updated = SEATS.map((s) =>
      s.id === 'A1' ? { ...s, status: 'taken' as const } : s,
    )
    updateSeats(updated)
    expect(isSelected('A1')).toBe(false)
    expect(mockToastShow).toHaveBeenCalledWith({
      message: 'Seat A1 is no longer available',
      type: 'error',
    })
  })

  it('moveFocus navigates right within row', async () => {
    const { focusedSeatId, moveFocus } = await create()
    focusedSeatId.value = 'A1'
    moveFocus('right')
    expect(focusedSeatId.value).toBe('A2')
  })

  it('moveFocus navigates left within row', async () => {
    const { focusedSeatId, moveFocus } = await create()
    focusedSeatId.value = 'A2'
    moveFocus('left')
    expect(focusedSeatId.value).toBe('A1')
  })

  it('moveFocus wraps at row end (right from A3 to A1)', async () => {
    const { focusedSeatId, moveFocus } = await create()
    focusedSeatId.value = 'A3'
    moveFocus('right')
    expect(focusedSeatId.value).toBe('A1')
  })

  it('moveFocus navigates down to next row', async () => {
    const { focusedSeatId, moveFocus } = await create()
    focusedSeatId.value = 'A1'
    moveFocus('down')
    expect(focusedSeatId.value).toBe('B1')
  })

  it('moveFocus navigates up to previous row', async () => {
    const { focusedSeatId, moveFocus } = await create()
    focusedSeatId.value = 'B1'
    moveFocus('up')
    expect(focusedSeatId.value).toBe('A1')
  })

  it('moveFocus wraps vertically (down from B to A)', async () => {
    const { focusedSeatId, moveFocus } = await create()
    focusedSeatId.value = 'B1'
    moveFocus('down')
    expect(focusedSeatId.value).toBe('A1')
  })
})
