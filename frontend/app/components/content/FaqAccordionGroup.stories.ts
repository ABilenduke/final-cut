import type { Meta, StoryObj } from '@storybook/vue3'
import FaqAccordionGroup from './FaqAccordionGroup.vue'

const meta: Meta<typeof FaqAccordionGroup> = {
  title: 'Content/FaqAccordionGroup',
  component: FaqAccordionGroup,
  decorators: [
    () => ({
      template: '<div style="max-width: 40rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof FaqAccordionGroup>

export const Default: Story = {
  render: () => ({
    components: { FaqAccordionGroup },
    setup: () => ({
      category: 'Tickets & Booking',
      items: [
        {
          question: 'How do I purchase tickets?',
          answer: 'You can purchase tickets online through our website by selecting a movie, choosing your preferred showtime, picking your seats, and completing the checkout process.',
        },
        {
          question: 'Can I get a refund on my tickets?',
          answer: 'Refunds are available up to 2 hours before the showtime. Please contact our box office or use the booking lookup feature.',
        },
        {
          question: 'Is there a limit to how many tickets I can buy?',
          answer: 'You can purchase up to 10 tickets per transaction. For larger group bookings, please contact us.',
        },
      ],
    }),
    template: '<FaqAccordionGroup :category="category" :items="items" />',
  }),
}

export const SingleItem: Story = {
  render: () => ({
    components: { FaqAccordionGroup },
    setup: () => ({
      category: 'Policies',
      items: [
        {
          question: 'Can I bring outside food and drinks?',
          answer: 'Outside food and beverages are not permitted in our auditoriums.',
        },
      ],
    }),
    template: '<FaqAccordionGroup :category="category" :items="items" />',
  }),
}
