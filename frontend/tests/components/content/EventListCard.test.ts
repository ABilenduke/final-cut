import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import EventListCard from '~/components/content/EventListCard.vue'
import type { CalendarEvent } from '~/types/calendar-event'

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: '1',
    type: 'special_event',
    title: 'Director\'s Q&A Night',
    date: '2026-04-20',
    startTime: '2026-04-20T19:00:00Z',
    endTime: '2026-04-20T22:00:00Z',
    description: 'Join acclaimed director Sarah Chen for an evening of conversation about filmmaking.',
    movieSlug: null,
    imageUrl: 'https://example.com/event.jpg',
    slug: 'directors-qa-night',
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

describe('EventListCard', () => {
  it('renders event title', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent() },
    })
    expect(wrapper.find('.event-list-card__title').text()).toBe('Director\'s Q&A Night')
  })

  it('renders event image when imageUrl provided', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent() },
    })
    const img = wrapper.find('.event-list-card__image')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/event.jpg')
  })

  it('renders placeholder when no imageUrl', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent({ imageUrl: null }) },
    })
    expect(wrapper.find('.event-list-card__image').exists()).toBe(false)
    expect(wrapper.find('.event-list-card__image-placeholder').exists()).toBe(true)
  })

  it('renders date badge', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('Apr 20, 2026')
  })

  it('renders type badge', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('Special Event')
  })

  it('renders Members Only badge when loyaltyOnly', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent({ loyaltyOnly: true }) },
    })
    expect(wrapper.text()).toContain('Members Only')
  })

  it('does not render Members Only badge when not loyaltyOnly', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).not.toContain('Members Only')
  })

  it('truncates long descriptions', async () => {
    const longDesc = 'A'.repeat(200)
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent({ description: longDesc }) },
    })
    const description = wrapper.find('.event-list-card__description')
    expect(description.text().length).toBeLessThan(130)
    expect(description.text()).toContain('...')
  })

  it('does not truncate short descriptions', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent({ description: 'Short text' }) },
    })
    const description = wrapper.find('.event-list-card__description')
    expect(description.text()).toBe('Short text')
  })

  it('links to /events/:slug', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent({ slug: 'test-event' }) },
    })
    const card = wrapper.find('.cv-card')
    expect(card.attributes('href')).toBe('/events/test-event')
  })

  it('renders Learn More link text', async () => {
    const wrapper = await mountSuspended(EventListCard, {
      props: { event: makeEvent() },
    })
    expect(wrapper.text()).toContain('Learn More')
  })
})
