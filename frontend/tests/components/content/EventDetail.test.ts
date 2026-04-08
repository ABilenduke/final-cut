import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import EventDetail from '~/components/content/EventDetail.vue'
import type { CalendarEvent } from '~/types/calendar-event'

type EventDetailProps = CalendarEvent & { pricing?: string; includes?: string[] }

function makeEvent(overrides: Partial<EventDetailProps> = {}): EventDetailProps {
  return {
    id: '1',
    type: 'special_event',
    title: 'Director\'s Q&A Night',
    date: '2026-04-20',
    startTime: '2026-04-20T19:00:00Z',
    endTime: '2026-04-20T22:00:00Z',
    description: 'An evening of conversation about filmmaking.',
    movieSlug: null,
    imageUrl: null,
    slug: 'directors-qa-night',
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    pricing: '$25 per person',
    includes: ['Reserved seating', 'Welcome drink'],
    ...overrides,
  }
}

describe('EventDetail', () => {
  it('renders event title', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent() },
    })
    expect(wrapper.find('.event-detail__title').text()).toBe('Director\'s Q&A Night')
  })

  it('renders formatted date', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('Apr 20, 2026')
  })

  it('renders description', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('An evening of conversation about filmmaking.')
  })

  it('renders includes list', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('What\'s Included')
    expect(wrapper.text()).toContain('Reserved seating')
    expect(wrapper.text()).toContain('Welcome drink')
  })

  it('does not render includes section when empty', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent({ includes: undefined }) },
    })
    expect(wrapper.text()).not.toContain('What\'s Included')
  })

  it('renders pricing section', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('Pricing')
    expect(wrapper.text()).toContain('$25 per person')
  })

  it('does not render pricing section when not provided', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent({ pricing: undefined }) },
    })
    expect(wrapper.text()).not.toContain('Pricing')
  })

  it('renders Get Tickets button when ticketUrl provided', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent({ ticketUrl: '/purchase/st-123' }) },
    })
    expect(wrapper.text()).toContain('Get Tickets')
    expect(wrapper.text()).not.toContain('RSVP')
  })

  it('renders RSVP button when no ticketUrl', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('RSVP')
    expect(wrapper.text()).not.toContain('Get Tickets')
  })

  it('renders accessibility badges', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: {
        event: makeEvent({
          accessibilityTags: ['sensory_friendly', 'open_caption'],
        }),
      },
    })
    expect(wrapper.text()).toContain('Sensory Friendly')
    expect(wrapper.text()).toContain('Open Captions')
  })

  it('renders Members Only badge when loyaltyOnly', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent({ loyaltyOnly: true }) },
    })
    expect(wrapper.text()).toContain('Members Only')
  })

  it('renders time range with datetime attribute', async () => {
    const wrapper = await mountSuspended(EventDetail, {
      props: { event: makeEvent() },
    })
    const time = wrapper.find('time')
    expect(time.exists()).toBe(true)
    expect(time.attributes('datetime')).toBe('2026-04-20T19:00:00Z')
  })
})
