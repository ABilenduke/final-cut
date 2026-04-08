import type { Meta, StoryObj } from '@storybook/vue3'
import LoyaltyPointsCard from './LoyaltyPointsCard.vue'

const meta: Meta<typeof LoyaltyPointsCard> = {
  title: 'Account/LoyaltyPointsCard',
  component: LoyaltyPointsCard,
  decorators: [
    () => ({
      template: '<div style="max-width: 20rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof LoyaltyPointsCard>

export const Member: Story = {
  render: () => ({
    components: { LoyaltyPointsCard },
    template: '<LoyaltyPointsCard :points="250" tier="member" :premier-expiry="null" />',
  }),
}

export const Premier: Story = {
  render: () => ({
    components: { LoyaltyPointsCard },
    template: '<LoyaltyPointsCard :points="1500" tier="premier" premier-expiry="2027-03-15" />',
  }),
}

export const NewMember: Story = {
  render: () => ({
    components: { LoyaltyPointsCard },
    template: '<LoyaltyPointsCard :points="0" tier="member" :premier-expiry="null" />',
  }),
}
