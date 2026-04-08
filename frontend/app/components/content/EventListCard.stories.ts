import type { Meta, StoryObj } from '@storybook/vue3'
import type { CalendarEvent } from '~/types/calendar-event'
import EventListCard from './EventListCard.vue'

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: '1',
    type: 'special_event',
    title: 'Director\'s Q&A Night',
    date: '2026-04-20',
    startTime: '2026-04-20T19:00:00Z',
    endTime: '2026-04-20T22:00:00Z',
    description: 'Join acclaimed director Sarah Chen for an evening of conversation about the craft of filmmaking, followed by a screening of her latest work.',
    movieSlug: null,
    imageUrl: 'https://image.tmdb.org/t/p/w500/example-event.jpg',
    slug: 'directors-qa-night',
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

const meta: Meta<typeof EventListCard> = {
  title: 'Content/EventListCard',
  component: EventListCard,
  decorators: [
    () => ({
      template: '<div style="max-width: 20rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof EventListCard>

export const Default: Story = {
  render: () => ({
    components: { EventListCard },
    setup: () => ({ event: makeEvent() }),
    template: '<EventListCard :event="event" />',
  }),
}

export const LoyaltyOnly: Story = {
  render: () => ({
    components: { EventListCard },
    setup: () => ({
      event: makeEvent({
        title: 'Premier Preview: Exclusive Screening',
        loyaltyOnly: true,
        type: 'loyalty_exclusive',
      }),
    }),
    template: '<EventListCard :event="event" />',
  }),
}

export const NoImage: Story = {
  render: () => ({
    components: { EventListCard },
    setup: () => ({
      event: makeEvent({ imageUrl: null }),
    }),
    template: '<EventListCard :event="event" />',
  }),
}

export const LongDescription: Story = {
  render: () => ({
    components: { EventListCard },
    setup: () => ({
      event: makeEvent({
        description: 'This is a very long description that should be truncated at 120 characters. It contains so much detail about the event that it would overflow the card if displayed in full.',
      }),
    }),
    template: '<EventListCard :event="event" />',
  }),
}
