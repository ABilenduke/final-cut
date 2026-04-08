import type { Meta, StoryObj } from '@storybook/vue3'
import ContactMap from './ContactMap.vue'

const meta: Meta<typeof ContactMap> = {
  title: 'Content/ContactMap',
  component: ContactMap,
  decorators: [
    () => ({
      template: '<div style="max-width: 40rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof ContactMap>

export const Default: Story = {
  render: () => ({
    components: { ContactMap },
    setup: () => ({
      coordinates: { lat: 40.7128, lng: 73.9352 },
      address: '123 Cinema Boulevard, New York, NY 10001',
    }),
    template: '<ContactMap :coordinates="coordinates" :address="address" />',
  }),
}

export const NoAddress: Story = {
  render: () => ({
    components: { ContactMap },
    setup: () => ({
      coordinates: { lat: 34.0522, lng: 118.2437 },
    }),
    template: '<ContactMap :coordinates="coordinates" />',
  }),
}
