import type { Meta, StoryObj } from '@storybook/vue3'
import type { CalendarEvent } from '~/types/calendar-event'
import CalendarDayCell from './CalendarDayCell.vue'

function makeEvent(overrides: Partial<CalendarEvent> = {}): CalendarEvent {
  return {
    id: '1',
    type: 'showtime',
    title: 'Test Event',
    date: '2026-04-15',
    startTime: '2026-04-15T19:00:00Z',
    endTime: '2026-04-15T21:00:00Z',
    description: 'A test event',
    movieSlug: null,
    imageUrl: null,
    slug: null,
    ticketUrl: null,
    loyaltyOnly: false,
    accessibilityTags: [],
    ...overrides,
  }
}

const meta: Meta<typeof CalendarDayCell> = {
  title: 'Calendar/CalendarDayCell',
  component: CalendarDayCell,
  decorators: [
    () => ({
      template: '<div style="width: 4rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof CalendarDayCell>

export const Default: Story = {
  render: () => ({
    components: { CalendarDayCell },
    setup: () => ({ events: [] as CalendarEvent[] }),
    template: '<CalendarDayCell date="2026-04-15" :events="events" :selected="false" :today="false" />',
  }),
}

export const Today: Story = {
  render: () => ({
    components: { CalendarDayCell },
    setup: () => ({ events: [] as CalendarEvent[] }),
    template: '<CalendarDayCell date="2026-04-08" :events="events" :selected="false" :today="true" />',
  }),
}

export const Selected: Story = {
  render: () => ({
    components: { CalendarDayCell },
    setup: () => ({ events: [] as CalendarEvent[] }),
    template: '<CalendarDayCell date="2026-04-15" :events="events" :selected="true" :today="false" />',
  }),
}

export const WithEvents: Story = {
  render: () => ({
    components: { CalendarDayCell },
    setup: () => ({
      events: [
        makeEvent({ type: 'showtime' }),
        makeEvent({ id: '2', type: 'special_event' }),
        makeEvent({ id: '3', type: 'loyalty_exclusive' }),
      ],
    }),
    template: '<CalendarDayCell date="2026-04-15" :events="events" :selected="false" :today="false" />',
  }),
}

export const OutsideMonth: Story = {
  render: () => ({
    components: { CalendarDayCell },
    setup: () => ({ events: [] as CalendarEvent[] }),
    template: '<CalendarDayCell date="2026-03-31" :events="events" :selected="false" :today="false" :outside-month="true" />',
  }),
}

export const WithAccessibilityEvent: Story = {
  render: () => ({
    components: { CalendarDayCell },
    setup: () => ({
      events: [makeEvent({ accessibilityTags: ['sensory_friendly'] })],
    }),
    template: '<CalendarDayCell date="2026-04-15" :events="events" :selected="false" :today="false" />',
  }),
}
