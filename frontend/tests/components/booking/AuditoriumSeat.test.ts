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
    expect(button.classes()).not.toContain('auditorium-seat--held')
  })

  it('renders the seat number as the default glyph', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ number: 7 }), selected: false, focused: false },
    })
    expect(wrapper.find('.auditorium-seat__num').text()).toBe('7')
  })

  it('renders selected state with --selected class', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat(), selected: true, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--selected')
    expect(button.attributes('aria-selected')).toBe('true')
  })

  it('renders taken state with --taken class and aria-disabled, NOT native disabled', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'taken' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--taken')
    expect(button.attributes('aria-disabled')).toBe('true')
    // Must NOT use native disabled — needed for roving tabindex
    expect(button.attributes('disabled')).toBeUndefined()
  })

  it('renders held state with its own --held class and aria-disabled', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'held' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--held')
    expect(button.classes()).not.toContain('auditorium-seat--taken')
    expect(button.attributes('aria-disabled')).toBe('true')
  })

  it('renders accessible seat with ♿ glyph and --accessible class', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ type: 'accessible' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--accessible')
    expect(wrapper.find('.auditorium-seat__glyph').text()).toBe('♿')
  })

  it('renders premium seat with --premiere (premiere recliner) visual class', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ type: 'premium' }), selected: false, focused: false },
    })
    const button = wrapper.find('button')
    expect(button.classes()).toContain('auditorium-seat--premiere')
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

  it('does NOT emit toggle on click when held', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'held' }), selected: false, focused: false },
    })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('toggle')).toBeUndefined()
  })

  it('has aria-label with seat info using the tier label', async () => {
    const seat = makeSeat({ id: 'B3', row: 'B', number: 3, price: 1800, type: 'premium' })
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat, selected: false, focused: false },
    })
    const label = wrapper.find('button').attributes('aria-label')
    expect(label).toContain('Seat B3')
    expect(label).toContain('available')
    expect(label).toContain('Row B')
    expect(label).toContain('seat 3')
    expect(label).toContain('Premiere recliner')
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

  it('aria-label reflects on-hold status for held seats', async () => {
    const wrapper = await mountSuspended(AuditoriumSeat, {
      props: { seat: makeSeat({ status: 'held' }), selected: false, focused: false },
    })
    const label = wrapper.find('button').attributes('aria-label')
    expect(label).toContain('on hold')
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
