import type { Meta, StoryObj } from '@storybook/vue3'
import CvAccordion from './CvAccordion.vue'

const meta: Meta<typeof CvAccordion> = {
  title: 'UI/CvAccordion',
  component: CvAccordion,
}

export default meta
type Story = StoryObj<typeof CvAccordion>

export const Default: Story = {
  render: () => ({
    components: { CvAccordion },
    template: `
      <div style="max-width: 30rem;">
        <CvAccordion id="demo-1" title="Can I get a refund on my tickets?">
          <p style="color: var(--tertiary); margin: 0;">Tickets can be refunded up to 2 hours before the showtime. After that, exchanges may be available depending on seat availability.</p>
        </CvAccordion>
      </div>
    `,
  }),
}

export const InitiallyOpen: Story = {
  render: () => ({
    components: { CvAccordion },
    template: `
      <div style="max-width: 30rem;">
        <CvAccordion id="demo-2" title="What food can I bring?" :open="true">
          <p style="color: var(--tertiary); margin: 0;">Outside food and beverages are not permitted. We offer a full concession menu including popcorn, drinks, candy, and combo meals.</p>
        </CvAccordion>
      </div>
    `,
  }),
}

export const Group: Story = {
  render: () => ({
    components: { CvAccordion },
    template: `
      <div style="max-width: 30rem; display: flex; flex-direction: column; gap: 0.5rem;">
        <CvAccordion id="faq-1" title="How do I purchase tickets?">
          <p style="color: var(--tertiary); margin: 0;">Select your movie, choose a showtime, pick your seats, and complete checkout.</p>
        </CvAccordion>
        <CvAccordion id="faq-2" title="Is there accessible seating?">
          <p style="color: var(--tertiary); margin: 0;">Yes, wheelchair-accessible seats and companion seats are available in every auditorium.</p>
        </CvAccordion>
        <CvAccordion id="faq-3" title="What are the age restrictions?">
          <p style="color: var(--tertiary); margin: 0;">Age restrictions follow MPAA ratings. Children under 17 require a parent for R-rated films.</p>
        </CvAccordion>
      </div>
    `,
  }),
}
