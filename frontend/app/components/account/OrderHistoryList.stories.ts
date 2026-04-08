import type { Meta, StoryObj } from '@storybook/vue3'
import type { Booking } from '~/types/booking'
import OrderHistoryList from './OrderHistoryList.vue'

function mockBooking(overrides: Partial<Booking> = {}): Booking {
  return {
    id: '1',
    confirmationCode: 'CVF-A3X9K2',
    showtimeId: 'st-1',
    movieTitle: 'Blade Runner 2099',
    moviePosterUrl: 'https://example.com/poster.jpg',
    screenName: 'Screen 1',
    startTime: '2026-04-03T19:00:00Z',
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

const meta: Meta<typeof OrderHistoryList> = {
  title: 'Account/OrderHistoryList',
  component: OrderHistoryList,
  decorators: [
    () => ({
      template: '<div style="max-width: 40rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof OrderHistoryList>

export const Empty: Story = {
  render: () => ({
    components: { OrderHistoryList },
    template: '<OrderHistoryList :orders="[]" :total="0" :current-page="1" :per-page="10" />',
  }),
}

export const SingleOrder: Story = {
  render: () => ({
    components: { OrderHistoryList },
    setup: () => ({
      orders: [mockBooking()],
    }),
    template: '<OrderHistoryList :orders="orders" :total="1" :current-page="1" :per-page="10" />',
  }),
}

export const MultipleOrders: Story = {
  render: () => ({
    components: { OrderHistoryList },
    setup: () => ({
      orders: [
        mockBooking(),
        mockBooking({
          id: '2',
          confirmationCode: 'CVF-B7Y4M1',
          movieTitle: 'Dune: Part Three',
          total: 3200,
          subtotal: 3200,
          seats: [
            { seatId: 'C5', section: 'Premium', price: 1600 },
            { seatId: 'C6', section: 'Premium', price: 1600 },
          ],
          createdAt: '2026-03-20T10:00:00Z',
        }),
        mockBooking({
          id: '3',
          confirmationCode: 'CVF-D2K8P5',
          movieTitle: 'The Matrix Resurrections 2',
          total: 1500,
          subtotal: 1500,
          status: 'cancelled',
          createdAt: '2026-03-15T14:00:00Z',
        }),
      ],
    }),
    template: '<OrderHistoryList :orders="orders" :total="15" :current-page="1" :per-page="3" />',
  }),
}

export const WithFoodItems: Story = {
  render: () => ({
    components: { OrderHistoryList },
    setup: () => ({
      orders: [
        mockBooking({
          foodItems: [
            { itemId: 'f1', name: 'Large Popcorn', quantity: 1, unitPrice: 850, totalPrice: 850 },
            { itemId: 'f2', name: 'Cola', quantity: 2, unitPrice: 550, totalPrice: 1100 },
          ],
          subtotal: 3450,
          total: 3450,
        }),
      ],
    }),
    template: '<OrderHistoryList :orders="orders" :total="1" :current-page="1" :per-page="10" />',
  }),
}
