import type { Meta, StoryObj } from '@storybook/vue3'
import type { Booking } from '~/types/booking'
import UpcomingBookings from './UpcomingBookings.vue'

function mockBooking(overrides: Partial<Booking> = {}): Booking {
  return {
    id: '1',
    confirmationCode: 'CVF-A3X9K2',
    showtimeId: 'st-1',
    movieTitle: 'Blade Runner 2099',
    moviePosterUrl: 'https://image.tmdb.org/t/p/w500/example-poster.jpg',
    screenName: 'Screen 1',
    startTime: '2026-04-10T19:00:00Z',
    seats: [{ seatId: 'A1', section: 'Standard', price: 1500 }],
    foodItems: [],
    subtotal: 1500,
    discount: 0,
    total: 1500,
    paymentMethod: 'card',
    userId: '1',
    guestEmail: null,
    status: 'confirmed',
    createdAt: '2026-04-01T12:00:00Z',
    ...overrides,
  }
}

const meta: Meta<typeof UpcomingBookings> = {
  title: 'Account/UpcomingBookings',
  component: UpcomingBookings,
  decorators: [
    () => ({
      template: '<div style="max-width: 30rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof UpcomingBookings>

export const Empty: Story = {
  render: () => ({
    components: { UpcomingBookings },
    template: '<UpcomingBookings :bookings="[]" />',
  }),
}

export const SingleBooking: Story = {
  render: () => ({
    components: { UpcomingBookings },
    setup: () => ({
      bookings: [mockBooking()],
    }),
    template: '<UpcomingBookings :bookings="bookings" />',
  }),
}

export const MultipleBookings: Story = {
  render: () => ({
    components: { UpcomingBookings },
    setup: () => ({
      bookings: [
        mockBooking(),
        mockBooking({
          id: '2',
          confirmationCode: 'CVF-B7Y4M1',
          movieTitle: 'Dune: Part Three',
          moviePosterUrl: 'https://image.tmdb.org/t/p/w500/example-dune.jpg',
          screenName: 'IMAX',
          startTime: '2026-04-12T14:30:00Z',
          seats: [
            { seatId: 'D3', section: 'Premium', price: 1800 },
            { seatId: 'D4', section: 'Premium', price: 1800 },
          ],
        }),
        mockBooking({
          id: '3',
          confirmationCode: 'CVF-D2K8P5',
          movieTitle: 'The Matrix Resurrections 2',
          moviePosterUrl: 'https://image.tmdb.org/t/p/w500/example-matrix.jpg',
          screenName: 'Screen 3',
          startTime: '2026-04-15T21:00:00Z',
          seats: [{ seatId: 'F7', section: 'Standard', price: 1500 }],
        }),
      ],
    }),
    template: '<UpcomingBookings :bookings="bookings" />',
  }),
}

export const NoPoster: Story = {
  render: () => ({
    components: { UpcomingBookings },
    setup: () => ({
      bookings: [mockBooking({ moviePosterUrl: '' })],
    }),
    template: '<UpcomingBookings :bookings="bookings" />',
  }),
}
