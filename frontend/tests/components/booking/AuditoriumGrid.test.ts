import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import AuditoriumGrid from '~/components/booking/AuditoriumGrid.vue'
import type { Auditorium, Seat } from '~/types/auditorium'

function makeAuditorium(): Auditorium {
  return {
    id: 'aud-1',
    name: 'Screen 1',
    rows: [
      { label: 'A', seats: [], section: 'Standard' },
      { label: 'B', seats: [], section: 'Standard' },
    ],
    seatsPerRow: 3,
    totalSeats: 6,
    sections: [{ name: 'Standard', priceMultiplier: 1 }],
  }
}

function makeSeats(): Seat[] {
  // The Auditorium grid tests address seats by `id` (UUID slot) but the new
  // shape also requires a `label` for display. Tests use label-shaped ids so
  // existing emit-payload assertions remain readable.
  return [
    // Row A: seat A1 is taken, A2 and A3 are available
    { id: 'A1', label: 'A1', row: 'A', number: 1, status: 'taken', type: 'standard', price: 1200 },
    { id: 'A2', label: 'A2', row: 'A', number: 2, status: 'available', type: 'standard', price: 1200 },
    { id: 'A3', label: 'A3', row: 'A', number: 3, status: 'available', type: 'standard', price: 1200 },
    // Row B: all available
    { id: 'B1', label: 'B1', row: 'B', number: 1, status: 'available', type: 'standard', price: 1200 },
    { id: 'B2', label: 'B2', row: 'B', number: 2, status: 'available', type: 'standard', price: 1200 },
    { id: 'B3', label: 'B3', row: 'B', number: 3, status: 'available', type: 'standard', price: 1200 },
  ]
}

describe('AuditoriumGrid', () => {
  it('renders rows with labels on both sides', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: [],
      },
    })
    const leftLabels = wrapper.findAll('.auditorium__label--left')
    const rightLabels = wrapper.findAll('.auditorium__label--right')
    expect(leftLabels).toHaveLength(2)
    expect(rightLabels).toHaveLength(2)
    expect(leftLabels[0].text()).toBe('A')
    expect(leftLabels[1].text()).toBe('B')
    expect(rightLabels[0].text()).toBe('A')
    expect(rightLabels[1].text()).toBe('B')
  })

  it('renders correct number of seat cells', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: [],
      },
    })
    const seatComponents = wrapper.findAllComponents({ name: 'AuditoriumSeat' })
    expect(seatComponents).toHaveLength(6)
  })

  it('has role="grid" with aria-label', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: [],
      },
    })
    const grid = wrapper.find('[role="grid"]')
    expect(grid.exists()).toBe(true)
    expect(grid.attributes('aria-label')).toBe('Theater seating chart, Screen 1')
  })

  it('emits seat-toggled when an available seat is clicked', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: [],
      },
    })
    // Find the seat component for A2 (available)
    const seatComponents = wrapper.findAllComponents({ name: 'AuditoriumSeat' })
    const a2Seat = seatComponents.find(c => c.props('seat').id === 'A2')
    expect(a2Seat).toBeDefined()
    a2Seat!.vm.$emit('toggle')
    await wrapper.vm.$nextTick()

    const emitted = wrapper.emitted('seat-toggled')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toEqual({ seatId: 'A2', selected: true })
  })

  it('does not emit seat-toggled when clicking a taken seat', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: [],
      },
    })
    // A1 is taken — click its button directly
    const seatButtons = wrapper.findAll('button.auditorium-seat')
    // A1 is the first button
    await seatButtons[0].trigger('click')

    expect(wrapper.emitted('seat-toggled')).toBeUndefined()
  })

  it('shows max seat limit message when 10 seats selected', async () => {
    const selectedIds = Array.from({ length: 10 }, (_, i) => `X${i}`)
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: selectedIds,
      },
    })
    const limitMsg = wrapper.find('.auditorium-grid-wrapper__limit')
    expect(limitMsg.exists()).toBe(true)
    expect(limitMsg.text()).toContain('Maximum 10 seats per transaction')
  })

  it('does not show limit message when fewer than 10 seats selected', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: ['A2'],
      },
    })
    const limitMsg = wrapper.find('.auditorium-grid-wrapper__limit')
    expect(limitMsg.exists()).toBe(false)
  })

  it('initial focus goes to first available seat, not first seat', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(), // A1 is taken, A2 is first available
        selectedSeatIds: [],
      },
    })
    const seatComponents = wrapper.findAllComponents({ name: 'AuditoriumSeat' })
    // A1 (taken) should NOT have focus
    const a1 = seatComponents.find(c => c.props('seat').id === 'A1')
    expect(a1!.props('focused')).toBe(false)
    // A2 (first available) should have focus
    const a2 = seatComponents.find(c => c.props('seat').id === 'A2')
    expect(a2!.props('focused')).toBe(true)
  })

  it('renders rows with role="row" and aria-label', async () => {
    const wrapper = await mountSuspended(AuditoriumGrid, {
      props: {
        auditorium: makeAuditorium(),
        seats: makeSeats(),
        selectedSeatIds: [],
      },
    })
    const rows = wrapper.findAll('[role="row"]')
    expect(rows).toHaveLength(2)
    expect(rows[0].attributes('aria-label')).toBe('Row A')
    expect(rows[1].attributes('aria-label')).toBe('Row B')
  })
})
