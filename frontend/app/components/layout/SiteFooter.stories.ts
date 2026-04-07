import type { Meta, StoryObj } from '@storybook/vue3'
import SiteFooter from './SiteFooter.vue'

const meta: Meta<typeof SiteFooter> = {
  title: 'Layout/SiteFooter',
  component: SiteFooter,
  parameters: {
    layout: 'fullscreen',
  },
}

export default meta
type Story = StoryObj<typeof SiteFooter>

export const Default: Story = {
  render: () => ({
    components: { SiteFooter },
    template: '<SiteFooter />',
  }),
}
