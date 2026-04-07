import type { Meta, StoryObj } from '@storybook/vue3'
import HomeEventStrip from './HomeEventStrip.vue'

const meta: Meta<typeof HomeEventStrip> = {
  title: 'Home/HomeEventStrip',
  component: HomeEventStrip,
  parameters: {
    layout: 'fullscreen',
    docs: {
      description: {
        component:
          'Self-contained "What\'s On This Week" section for the homepage. Fetches events via useCalendarEvents, filters to the current week, and renders a compact list. Hidden when no qualifying events exist (special_event and loyalty_exclusive types only).',
      },
    },
  },
}

export default meta
type Story = StoryObj<typeof HomeEventStrip>

export const Default: Story = {}
