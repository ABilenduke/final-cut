import type { Meta, StoryObj } from '@storybook/vue3'
import type { CalendarEvent } from '~/types/calendar-event'
import EventDetail from './EventDetail.vue'

type EventDetailProps = CalendarEvent & { pricing?: string; includes?: string[] }

function makeEvent(overrides: Partial<EventDetailProps> = {}): EventDetailProps {
  return {
    id: '1',
    type: 'special_event',
    title: 'Director\'s Q&A Night',
    date: '2026-04-20',
    startTime: '2026-04-20T19:00:00Z',
    endTime: '2026-04-20T22:00:00Z',
    description: 'Join acclaimed director Sarah Chen for an evening of conversation about the craft of filmmaking, followed by a screening of her latest work. This exclusive event includes a moderated Q&A session and a chance to meet the director.',
    movieSlug: null,
    imageUrl: 'https://image.tmdb.org/t/p/w500/example-event.jpg',
    slug: 'directors-qa-night',
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    pricing: '$25 per person',
    includes: [
      'Reserved seating',
      'Welcome drink',
      'Q&A session with the director',
      'Exclusive screening',
    ],
    ...overrides,
  }
}

const meta: Meta<typeof EventDetail> = {
  title: 'Content/EventDetail',
  component: EventDetail,
  decorators: [
    () => ({
      template: '<div style="max-width: 40rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof EventDetail>

export const Default: Story = {
  render: () => ({
    components: { EventDetail },
    setup: () => ({ event: makeEvent() }),
    template: '<EventDetail :event="event" />',
  }),
}

export const WithTicketUrl: Story = {
  render: () => ({
    components: { EventDetail },
    setup: () => ({
      event: makeEvent({ ticketUrl: '/purchase/st-456' }),
    }),
    template: '<EventDetail :event="event" />',
  }),
}

export const LoyaltyExclusive: Story = {
  render: () => ({
    components: { EventDetail },
    setup: () => ({
      event: makeEvent({
        type: 'loyalty_exclusive',
        title: 'Premier Preview Screening',
        loyaltyOnly: true,
        accessibilityTags: ['sensory_friendly'],
      }),
    }),
    template: '<EventDetail :event="event" />',
  }),
}

export const Minimal: Story = {
  render: () => ({
    components: { EventDetail },
    setup: () => ({
      event: makeEvent({
        pricing: undefined,
        includes: undefined,
        endTime: null,
      }),
    }),
    template: '<EventDetail :event="event" />',
  }),
}
