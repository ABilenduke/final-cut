import type { Meta, StoryObj } from '@storybook/vue3'
import type { CalendarEvent } from '~/types/calendar-event'
import CalendarGrid from './CalendarGrid.vue'

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

const sampleEvents: CalendarEvent[] = [
  makeEvent({ id: '1', date: '2026-04-05', startTime: '2026-04-05T19:00:00Z' }),
  makeEvent({ id: '2', date: '2026-04-05', startTime: '2026-04-05T21:30:00Z', title: 'Dune: Part Three' }),
  makeEvent({ id: '3', date: '2026-04-12', startTime: '2026-04-12T19:00:00Z', type: 'special_event', title: 'Director\'s Q&A', slug: 'directors-qa' }),
  makeEvent({ id: '4', date: '2026-04-18', startTime: '2026-04-18T20:00:00Z', type: 'loyalty_exclusive', title: 'Premier Preview', loyaltyOnly: true }),
  makeEvent({ id: '5', date: '2026-04-22', startTime: '2026-04-22T14:00:00Z', title: 'Sensory Friendly Show', accessibilityTags: ['sensory_friendly'] }),
  makeEvent({ id: '6', date: '2026-04-22', startTime: '2026-04-22T19:00:00Z' }),
]

const meta: Meta<typeof CalendarGrid> = {
  title: 'Calendar/CalendarGrid',
  component: CalendarGrid,
}

export default meta
type Story = StoryObj<typeof CalendarGrid>

export const MonthView: Story = {
  render: () => ({
    components: { CalendarGrid },
    data: () => ({
      selectedDate: '2026-04-08',
      month: 4,
      year: 2026,
      events: sampleEvents,
    }),
    methods: {
      onSelectDate(date: string) { this.selectedDate = date },
      onNavigate(payload: { month: number; year: number }) {
        this.month = payload.month
        this.year = payload.year
      },
    },
    template: `
      <CalendarGrid
        :events="events"
        :selected-date="selectedDate"
        view="month"
        :month="month"
        :year="year"
        @select-date="onSelectDate"
        @navigate="onNavigate"
      />
    `,
  }),
}

export const WeekView: Story = {
  render: () => ({
    components: { CalendarGrid },
    data: () => ({
      selectedDate: '2026-04-08',
      month: 4,
      year: 2026,
      events: sampleEvents,
    }),
    methods: {
      onSelectDate(date: string) { this.selectedDate = date },
      onNavigate(payload: { month: number; year: number }) {
        this.month = payload.month
        this.year = payload.year
      },
    },
    template: `
      <CalendarGrid
        :events="events"
        :selected-date="selectedDate"
        view="week"
        :month="month"
        :year="year"
        @select-date="onSelectDate"
        @navigate="onNavigate"
      />
    `,
  }),
}

export const ListView: Story = {
  render: () => ({
    components: { CalendarGrid },
    data: () => ({
      selectedDate: '2026-04-08',
      month: 4,
      year: 2026,
      events: sampleEvents,
    }),
    methods: {
      onSelectDate(date: string) { this.selectedDate = date },
      onNavigate(payload: { month: number; year: number }) {
        this.month = payload.month
        this.year = payload.year
      },
    },
    template: `
      <CalendarGrid
        :events="events"
        :selected-date="selectedDate"
        view="list"
        :month="month"
        :year="year"
        @select-date="onSelectDate"
        @navigate="onNavigate"
      />
    `,
  }),
}

export const EmptyMonth: Story = {
  render: () => ({
    components: { CalendarGrid },
    data: () => ({
      selectedDate: '2026-04-08',
      month: 4,
      year: 2026,
    }),
    methods: {
      onSelectDate(date: string) { this.selectedDate = date },
      onNavigate(payload: { month: number; year: number }) {
        this.month = payload.month
        this.year = payload.year
      },
    },
    template: `
      <CalendarGrid
        :events="[]"
        :selected-date="selectedDate"
        view="month"
        :month="month"
        :year="year"
        @select-date="onSelectDate"
        @navigate="onNavigate"
      />
    `,
  }),
}
