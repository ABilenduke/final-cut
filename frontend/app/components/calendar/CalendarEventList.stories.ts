import type { Meta, StoryObj } from '@storybook/vue3'
import type { CalendarEvent } from '~/types/calendar-event'
import CalendarEventList from './CalendarEventList.vue'

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: '1',
    type: 'showtime',
    title: 'Blade Runner 2099',
    date: '2026-04-15',
    startTime: '2026-04-15T19:00:00Z',
    endTime: '2026-04-15T21:30:00Z',
    description: 'A futuristic thriller',
    movieSlug: 'blade-runner-2099',
    imageUrl: null,
    slug: null,
    ticketUrl: '/purchase/st-123',
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

const meta: Meta<typeof CalendarEventList> = {
  title: 'Calendar/CalendarEventList',
  component: CalendarEventList,
  decorators: [
    () => ({
      template: '<div style="max-width: 40rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof CalendarEventList>

export const WithEvents: Story = {
  render: () => ({
    components: { CalendarEventList },
    setup: () => ({
      events: [
        makeEvent(),
        makeEvent({ id: '2', title: 'Dune: Part Three', startTime: '2026-04-15T21:30:00Z' }),
        makeEvent({
          id: '3',
          type: 'special_event',
          title: 'Director\'s Q&A Night',
          startTime: '2026-04-15T20:00:00Z',
          slug: 'directors-qa',
          ticketUrl: null,
          movieSlug: null,
        }),
        makeEvent({
          id: '4',
          type: 'loyalty_exclusive',
          title: 'Premier Preview Screening',
          startTime: '2026-04-15T18:00:00Z',
          loyaltyOnly: true,
          movieSlug: null,
          ticketUrl: null,
        }),
      ],
    }),
    template: '<CalendarEventList :events="events" date="2026-04-15" />',
  }),
}

export const Empty: Story = {
  render: () => ({
    components: { CalendarEventList },
    template: '<CalendarEventList :events="[]" date="2026-04-15" />',
  }),
}

export const WithAccessibilityTags: Story = {
  render: () => ({
    components: { CalendarEventList },
    setup: () => ({
      events: [
        makeEvent({
          title: 'Sensory Friendly Screening',
          accessibilityTags: ['sensory_friendly'],
        }),
        makeEvent({
          id: '2',
          title: 'Open Caption Showing',
          accessibilityTags: ['open_caption', 'audio_described'],
          startTime: '2026-04-15T14:00:00Z',
        }),
      ],
    }),
    template: '<CalendarEventList :events="events" date="2026-04-15" />',
  }),
}
