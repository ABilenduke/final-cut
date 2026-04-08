import type { Meta, StoryObj } from '@storybook/vue3'
import SavedPaymentMethods from './SavedPaymentMethods.vue'

const mockMethods = [
  { id: '1', brand: 'visa', last4: '4242', expMonth: 12, expYear: 2027 },
  { id: '2', brand: 'mastercard', last4: '5555', expMonth: 6, expYear: 2028 },
]

const meta: Meta<typeof SavedPaymentMethods> = {
  title: 'Account/SavedPaymentMethods',
  component: SavedPaymentMethods,
  decorators: [
    () => ({
      template: '<div style="max-width: 30rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof SavedPaymentMethods>

export const Empty: Story = {
  render: () => ({
    components: { SavedPaymentMethods },
    template: '<SavedPaymentMethods :methods="[]" />',
  }),
}

export const SingleCard: Story = {
  render: () => ({
    components: { SavedPaymentMethods },
    setup: () => ({
      methods: [mockMethods[0]],
    }),
    template: '<SavedPaymentMethods :methods="methods" />',
  }),
}

export const MultipleCards: Story = {
  render: () => ({
    components: { SavedPaymentMethods },
    setup: () => ({
      methods: mockMethods,
    }),
    template: '<SavedPaymentMethods :methods="methods" />',
  }),
}
