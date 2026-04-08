import type { Meta, StoryObj } from '@storybook/vue3'
import BalanceChecker from './BalanceChecker.vue'

const meta: Meta<typeof BalanceChecker> = {
  title: 'Content/BalanceChecker',
  component: BalanceChecker,
  decorators: [
    () => ({
      template: '<div style="max-width: 25rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof BalanceChecker>

export const Default: Story = {
  render: () => ({
    components: { BalanceChecker },
    template: '<BalanceChecker />',
  }),
}
