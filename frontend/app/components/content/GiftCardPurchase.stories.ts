import type { Meta, StoryObj } from '@storybook/vue3'
import GiftCardPurchase from './GiftCardPurchase.vue'

const meta: Meta<typeof GiftCardPurchase> = {
  title: 'Content/GiftCardPurchase',
  component: GiftCardPurchase,
  decorators: [
    () => ({
      template: '<div style="max-width: 30rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof GiftCardPurchase>

export const Default: Story = {
  render: () => ({
    components: { GiftCardPurchase },
    template: '<GiftCardPurchase />',
  }),
}
