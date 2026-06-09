import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { ref } from 'vue'
import HomeRetrospectiveSplit from '~/components/home/HomeRetrospectiveSplit.vue'
import type { CalendarEvent } from '~/types/calendar-event'

// The component self-fetches the next upcoming special event via
// useCalendarEvents().getEvents() (current + next month).
const getEventsMock = vi.fn()

vi.mock('~/composables/useCalendarEvents', () => ({
  useCalendarEvents: () => ({ getEvents: getEventsMock }),
}))

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: 'k1',
    type: 'special_event',
    title: 'Kubrick in the grain',
    date: '2999-01-02',
    startTime: '2999-01-02T19:00:00+00:00',
    endTime: null,
    description: 'Four films. Three nights. Preserved 70mm prints.',
    movieSlug: null,
    imageUrl: null,
    slug: 'kubrick-in-the-grain',
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

// getEvents is called once per month (current + next). Returning the same ref for
// every call is harmless — the component sorts + takes the earliest upcoming event.
function mockEvents(events: CalendarEvent[]) {
  getEventsMock.mockReturnValue({ data: ref({ data: events }) })
}

beforeEach(() => {
  getEventsMock.mockReset()
})

describe('HomeRetrospectiveSplit', () => {
  it('renders the next special event with a split title and description', async () => {
    mockEvents([makeEvent()])
    const wrapper = await mountSuspended(HomeRetrospectiveSplit)

    const title = wrapper.find('.retro__title')
    expect(title.exists()).toBe(true)
    expect(title.text()).toContain('Kubrick')

    const em = title.find('em')
    expect(em.exists()).toBe(true)
    expect(em.text()).toBe('in the grain')

    expect(wrapper.find('.retro__copy').text()).toContain('Four films')
  })

  it('links the CTA to the event detail page', async () => {
    mockEvents([makeEvent()])
    const wrapper = await mountSuspended(HomeRetrospectiveSplit)
    expect(wrapper.find('a[href="/events/kubrick-in-the-grain"]').exists()).toBe(true)
  })

  it('falls back to the glyph when the event has no image', async () => {
    mockEvents([makeEvent({ imageUrl: null })])
    const wrapper = await mountSuspended(HomeRetrospectiveSplit)
    expect(wrapper.find('.retro__media-img').exists()).toBe(false)
    const glyph = wrapper.find('.retro__glyph')
    expect(glyph.exists()).toBe(true)
    expect(glyph.text()).toBe('K')
  })

  it('renders the banner image when the event has an imageUrl', async () => {
    mockEvents([makeEvent({ imageUrl: 'https://cdn.example/banner.webp' })])
    const wrapper = await mountSuspended(HomeRetrospectiveSplit)
    const img = wrapper.find('.retro__media-img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example/banner.webp')
    expect(wrapper.find('.retro__glyph').exists()).toBe(false)
  })

  it('hides the section when there are no upcoming special events', async () => {
    mockEvents([])
    const wrapper = await mountSuspended(HomeRetrospectiveSplit)
    expect(wrapper.find('.retro').exists()).toBe(false)
  })

  it('excludes events dated before today', async () => {
    mockEvents([
      makeEvent({ id: 'past', date: '2000-01-01', startTime: '2000-01-01T19:00:00+00:00' }),
    ])
    const wrapper = await mountSuspended(HomeRetrospectiveSplit)
    expect(wrapper.find('.retro').exists()).toBe(false)
  })
})
