import type { Meta, StoryObj } from '@storybook/vue3'
import NeuralTicker from './NeuralTicker.vue'

const meta: Meta<typeof NeuralTicker> = {
  title: 'Layout/NeuralTicker',
  component: NeuralTicker,
  parameters: {
    layout: 'fullscreen',
  },
}

export default meta
type Story = StoryObj<typeof NeuralTicker>

const sampleItems = [
  { text: 'The Void Beyond — 7:15 PM, Screen 1' },
  { text: 'Neon Requiem — 8:00 PM, Screen 2', href: '/movies/neon-requiem' },
  { text: 'Midnight Parallax — 9:30 PM, IMAX' },
  { text: 'New: Sensory Friendly screening this Saturday', href: '/whats-on?accessibility=sensory_friendly' },
  { text: 'Glass Meridian — Coming Soon', href: '/movies/glass-meridian' },
  { text: 'Premier members: Early access to summer blockbusters' },
]

export const Default: Story = {
  render: () => ({
    components: { NeuralTicker },
    setup: () => ({ sampleItems }),
    template: '<NeuralTicker :items="sampleItems" />',
  }),
}

export const FewItems: Story = {
  render: () => ({
    components: { NeuralTicker },
    setup: () => ({
      items: [
        { text: 'The Void Beyond — 7:15 PM' },
        { text: 'Now Showing: 3 films' },
      ],
    }),
    template: '<NeuralTicker :items="items" />',
  }),
}
