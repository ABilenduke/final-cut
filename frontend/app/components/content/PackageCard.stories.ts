import type { Meta, StoryObj } from '@storybook/vue3'
import PackageCard from './PackageCard.vue'

const meta: Meta<typeof PackageCard> = {
  title: 'Content/PackageCard',
  component: PackageCard,
  decorators: [
    () => ({
      template: '<div style="max-width: 20rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof PackageCard>

export const Birthday: Story = {
  render: () => ({
    components: { PackageCard },
    setup: () => ({
      pkg: {
        name: 'Birthday Party',
        description: 'Celebrate with a private screening for up to 30 guests.',
        startingPrice: 35000,
        features: ['Private auditorium', 'Custom on-screen message', 'Popcorn & drinks included', 'Party host provided'],
      },
    }),
    template: '<PackageCard :package="pkg" />',
  }),
}

export const Corporate: Story = {
  render: () => ({
    components: { PackageCard },
    setup: () => ({
      pkg: {
        name: 'Corporate Event',
        description: 'Team building, product launches, or client entertainment.',
        startingPrice: 75000,
        features: ['Full auditorium rental', 'AV equipment included', 'Catering options available', 'Presentation mode'],
      },
    }),
    template: '<PackageCard :package="pkg" />',
  }),
}
