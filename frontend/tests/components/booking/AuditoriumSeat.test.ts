import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import AuditoriumSeat from '~/components/booking/AuditoriumSeat.vue'
import type { Seat } from '~/types/auditorium'

function makeSeat(overrides: Partial<Seat> = {}): Seat {
  return {
    id: 'A1',
    row: 'A',
    number: 1,
    status: 'available',
    type: 'standard',
    price: 1200,
    ...overrides,
  }
}

describe('AuditoriumSeat', () => {
  it('renders with available state', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat(), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.exists()).toBe(true)
    expect(button.classes()).not.toContain('auditorium-seat--selected')
    expect(button.classes()).not.toContain('auditorium-seat--taken')
  })

  it('renders selected state with check icon', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat(), selected: true, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--selected')
    // Check icon is rendered via CvIcon with name="check"
    const icon = wrapper.findComponent({ name: 'CvIcon' })
    expect(icon.exists()).toBe(true)
    expect(icon.props('name')).toBe('check')
  })

  it('renders taken state with aria-disabled and --taken class, NOT native disabled', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'taken' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--taken')
    expect(button.attributes('aria-disabled')).toBe('true')
    // Must NOT use native disabled — needed for roving tabindex
    expect(button.attributes('disabled')).toBeUndefined()
  })

  it('renders held state the same as taken', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'held' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--taken')
    expect(button.attributes('aria-disabled')).toBe('true')
  })

  it('renders accessible seat with accessible icon', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ type: 'accessible' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--accessible')
    const icon = wrapper.findComponent({ name: 'CvIcon' })
    expect(icon.exists()).toBe(true)
    expect(icon.props('name')).toBe('accessible')
  })

  it('renders premium seat with --premium class', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ type: 'premium' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--premium')
  })

  it('emits toggle on click when available', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat(), selected: false, focused: false },
    })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('toggle')).toHaveLength(1)
  })

  it('does NOT emit toggle on click when taken', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'taken' }), selected: false, focused: false },
    })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('toggle')).toBeUndefined()
  })

  it('has aria-label with seat info', async () => {
    const seat = makeSeat({ id: 'B3', row: 'B', number: 3, price: 1800, type: 'premium' })
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat, selected: false, focused: false },
    })
    const label = wrapper.find('button').attributes('aria-label')
    expect(label).toContain('Seat B3')
    expect(label).toContain('available')
    expect(label).toContain('Row B')
    expect(label).toContain('seat 3')
    expect(label).toContain('Premium')
    expect(label).toContain('$18.00')
  })

  it('aria-label reflects selected status', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat(), selected: true, focused: false },
    })
    const label = wrapper.find('button').attributes('aria-label')
    expect(label).toContain('selected')
  })

  it('aria-label reflects unavailable status for taken seats', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'taken' }), selected: false, focused: false },
    })
    const label = wrapper.find('button').attributes('aria-label')
    expect(label).toContain('unavailable')
  })

  it('focused prop sets tabindex to 0', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat(), selected: false, focused: true },
    })
    expect(wrapper.find('button').attributes('tabindex')).toBe('0')
  })

  it('non-focused prop sets tabindex to -1', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat(), selected: false, focused: false },
    })
    expect(wrapper.find('button').attributes('tabindex')).toBe('-1')
  })
})
