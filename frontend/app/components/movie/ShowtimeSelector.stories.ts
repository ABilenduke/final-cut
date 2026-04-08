import type { Meta, StoryObj } from '@storybook/vue3'
import type { Showtime } from '~/types/showtime'
import ShowtimeSelector from './ShowtimeSelector.vue'

function makeShowtime(overrides: Partial<Showtime> = {}): Showtime {
  return {
    id: 'st-1',
    movieId: 1,
    movieSlug: 'blade-runner-2099',
    movieTitle: 'Blade Runner 2099',
    screenId: 's1',
    screenName: 'Screen 1',
    startTime: '2026-04-07T19:00:00Z',
    endTime: '2026-04-07T21:00:00Z',
    priceStandard: 1500,
    pricePremium: 2000,
    priceAccessible: 1200,
    ...overrides,
  }
}

const meta: Meta<typeof ShowtimeSelector> = {
  title: 'Movie/ShowtimeSelector',
  component: ShowtimeSelector,
  decorators: [
    () => ({
      template: '<div style="max-width: 22rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof ShowtimeSelector>

export const Default: Story = {
  render: () => ({
    components: { ShowtimeSelector },
    setup: () => ({
      showtimes: [
        makeShowtime({ id: 'st-1', startTime: '2026-04-07T14:00:00Z', endTime: '2026-04-07T16:00:00Z' }),
        makeShowtime({ id: 'st-2', startTime: '2026-04-07T19:00:00Z', endTime: '2026-04-07T21:00:00Z' }),
        makeShowtime({ id: 'st-3', startTime: '2026-04-07T21:30:00Z', endTime: '2026-04-07T23:30:00Z' }),
        makeShowtime({ id: 'st-4', startTime: '2026-04-08T14:00:00Z', endTime: '2026-04-08T16:00:00Z' }),
        makeShowtime({ id: 'st-5', startTime: '2026-04-08T19:00:00Z', endTime: '2026-04-08T21:00:00Z' }),
        makeShowtime({ id: 'st-6', startTime: '2026-04-09T16:00:00Z', endTime: '2026-04-09T18:00:00Z' }),
        makeShowtime({ id: 'st-7', startTime: '2026-04-09T20:00:00Z', endTime: '2026-04-09T22:00:00Z' }),
        makeShowtime({ id: 'st-8', startTime: '2026-04-09T22:30:00Z', endTime: '2026-04-10T00:30:00Z' }),
      ],
    }),
    template: '<ShowtimeSelector :showtimes="showtimes" />',
  }),
}

export const SingleDate: Story = {
  render: () => ({
    components: { ShowtimeSelector },
    setup: () => ({
      showtimes: [
        makeShowtime({ id: 'st-1', startTime: '2026-04-10T11:00:00Z', endTime: '2026-04-10T13:00:00Z', priceStandard: 1200 }),
        makeShowtime({ id: 'st-2', startTime: '2026-04-10T14:00:00Z', endTime: '2026-04-10T16:00:00Z', priceStandard: 1500 }),
        makeShowtime({ id: 'st-3', startTime: '2026-04-10T19:00:00Z', endTime: '2026-04-10T21:00:00Z', priceStandard: 1800 }),
        makeShowtime({ id: 'st-4', startTime: '2026-04-10T22:00:00Z', endTime: '2026-04-11T00:00:00Z', priceStandard: 1800 }),
      ],
    }),
    template: '<ShowtimeSelector :showtimes="showtimes" />',
  }),
}

export const Empty: Story = {
  render: () => ({
    components: { ShowtimeSelector },
    template: '<ShowtimeSelector :showtimes="[]" />',
  }),
}

export const ManyDates: Story = {
  render: () => ({
    components: { ShowtimeSelector },
    setup: () => ({
      showtimes: [
        makeShowtime({ id: 'st-01', startTime: '2026-04-07T19:00:00Z', endTime: '2026-04-07T21:00:00Z' }),
        makeShowtime({ id: 'st-02', startTime: '2026-04-08T19:00:00Z', endTime: '2026-04-08T21:00:00Z' }),
        makeShowtime({ id: 'st-03', startTime: '2026-04-09T19:00:00Z', endTime: '2026-04-09T21:00:00Z' }),
        makeShowtime({ id: 'st-04', startTime: '2026-04-10T14:00:00Z', endTime: '2026-04-10T16:00:00Z' }),
        makeShowtime({ id: 'st-05', startTime: '2026-04-10T19:00:00Z', endTime: '2026-04-10T21:00:00Z' }),
        makeShowtime({ id: 'st-06', startTime: '2026-04-11T19:00:00Z', endTime: '2026-04-11T21:00:00Z' }),
        makeShowtime({ id: 'st-07', startTime: '2026-04-12T14:00:00Z', endTime: '2026-04-12T16:00:00Z' }),
        makeShowtime({ id: 'st-08', startTime: '2026-04-12T19:00:00Z', endTime: '2026-04-12T21:00:00Z' }),
        makeShowtime({ id: 'st-09', startTime: '2026-04-13T11:00:00Z', endTime: '2026-04-13T13:00:00Z' }),
        makeShowtime({ id: 'st-10', startTime: '2026-04-13T14:00:00Z', endTime: '2026-04-13T16:00:00Z' }),
        makeShowtime({ id: 'st-11', startTime: '2026-04-13T19:00:00Z', endTime: '2026-04-13T21:00:00Z' }),
      ],
    }),
    template: '<ShowtimeSelector :showtimes="showtimes" />',
  }),
}
