import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CalendarFilters from '~/components/calendar/CalendarFilters.vue'

describe('CalendarFilters', () => {
  it('renders view toggle with three options', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    const buttons = wrapper.findAll('[role="radio"]')
    expect(buttons).toHaveLength(3)
    expect(buttons[0].text()).toBe('Month')
    expect(buttons[1].text()).toBe('Week')
    expect(buttons[2].text()).toBe('List')
  })

  it('marks active view button with aria-checked', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'week',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    const buttons = wrapper.findAll('[role="radio"]')
    expect(buttons[0].attributes('aria-checked')).toBe('false')
    expect(buttons[1].attributes('aria-checked')).toBe('true')
    expect(buttons[2].attributes('aria-checked')).toBe('false')
  })

  it('emits view-change when clicking a view button', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    await wrapper.findAll('[role="radio"]')[2].trigger('click')
    expect(wrapper.emitted('view-change')).toEqual([['list']])
  })

  it('renders event type checkboxes with correct labels', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    const text = wrapper.text()
    expect(text).toContain('Showtimes')
    expect(text).toContain('Special Events')
    expect(text).toContain('Loyalty Exclusives')
  })

  it('renders accessibility checkboxes with correct labels', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    const text = wrapper.text()
    expect(text).toContain('Sensory Friendly')
    expect(text).toContain('Open Captions')
    expect(text).toContain('Audio Described')
  })

  it('emits filter-change when toggling an event type checkbox', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    // Find the first checkbox input in the Event Types section
    const checkboxes = wrapper.findAll('input[type="checkbox"]')
    // First 3 are event types, next 3 are accessibility
    await checkboxes[0].trigger('change')
    expect(wrapper.emitted('filter-change')?.[0]).toEqual([['showtime']])
  })

  it('emits filter-change removing a filter when already active', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: ['showtime', 'special_event'],
        activeAccessibilityFilters: [],
      },
    })
    const checkboxes = wrapper.findAll('input[type="checkbox"]')
    await checkboxes[0].trigger('change')
    expect(wrapper.emitted('filter-change')?.[0]).toEqual([['special_event']])
  })

  it('emits accessibility-filter-change when toggling an accessibility checkbox', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    const checkboxes = wrapper.findAll('input[type="checkbox"]')
    // Accessibility checkboxes are after the 3 event type ones
    await checkboxes[3].trigger('change')
    expect(wrapper.emitted('accessibility-filter-change')?.[0]).toEqual([['sensory_friendly']])
  })

  it('applies active class to active view button', async () => {
    const wrapper = await mountSuspended(CalendarFilters, {
      props: {
        activeView: 'month',
        activeFilters: [],
        activeAccessibilityFilters: [],
      },
    })
    const buttons = wrapper.findAll('[role="radio"]')
    expect(buttons[0].classes()).toContain('calendar-filters__view-btn--active')
    expect(buttons[1].classes()).not.toContain('calendar-filters__view-btn--active')
  })
})
