import type { Meta, StoryObj } from '@storybook/vue3'
import type { Location } from '~/types/location'
import QuickShowtimeStrip from './QuickShowtimeStrip.vue'

const mockLocation: Location = {
  id: '1',
  name: 'Downtown Theatre',
  slug: 'downtown-theatre',
  address: '123 Main St',
}

const meta: Meta<typeof QuickShowtimeStrip> = {
  title: 'Home/QuickShowtimeStrip',
  component: QuickShowtimeStrip,
}

export default meta
type Story = StoryObj<typeof QuickShowtimeStrip>

export const WithLocation: Story = {
  render: () => ({
    components: { QuickShowtimeStrip },
    setup: () => ({ location: mockLocation }),
    template: '<QuickShowtimeStrip :location="location" />',
  }),
}

export const NoLocation: Story = {
  render: () => ({
    components: { QuickShowtimeStrip },
    template: '<QuickShowtimeStrip :location="null" />',
  }),
}

export const TomorrowSelected: Story = {
  render: () => ({
    components: { QuickShowtimeStrip },
    setup: () => ({ location: mockLocation }),
    template: '<QuickShowtimeStrip :location="location" ref="strip" />',
    mounted() {
      // Click the "Tomorrow" pill after mount
      const pills = (this as any).$el.querySelectorAll('.showtime-strip__pill')
      if (pills[1]) pills[1].click()
    },
  }),
}
