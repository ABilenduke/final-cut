import type { Meta, StoryObj } from '@storybook/vue3'
import CvToast from './CvToast.vue'

const meta: Meta<typeof CvToast> = {
  title: 'UI/CvToast',
  component: CvToast,
}

export default meta
type Story = StoryObj<typeof CvToast>

export const Info: Story = {
  render: () => ({
    components: { CvToast },
    setup: () => ({
      toast: { id: '1', message: 'Your session will expire in 5 minutes.', type: 'info' as const, duration: 0 },
    }),
    template: '<CvToast :toast="toast" />',
  }),
}

export const Success: Story = {
  render: () => ({
    components: { CvToast },
    setup: () => ({
      toast: { id: '2', message: 'Seat A1 selected. 2 seats selected, $25.98 total.', type: 'success' as const, duration: 0 },
    }),
    template: '<CvToast :toast="toast" />',
  }),
}

export const Error: Story = {
  render: () => ({
    components: { CvToast },
    setup: () => ({
      toast: { id: '3', message: 'Payment declined. Please try another card.', type: 'error' as const, duration: 0 },
    }),
    template: '<CvToast :toast="toast" />',
  }),
}

export const AllTypes: Story = {
  render: () => ({
    components: { CvToast },
    setup: () => ({
      toasts: [
        { id: '1', message: 'Your session will expire soon.', type: 'info' as const, duration: 0 },
        { id: '2', message: 'Booking confirmed!', type: 'success' as const, duration: 0 },
        { id: '3', message: 'Seat is no longer available.', type: 'error' as const, duration: 0 },
      ],
    }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <CvToast v-for="t in toasts" :key="t.id" :toast="t" />
      </div>
    `,
  }),
}
