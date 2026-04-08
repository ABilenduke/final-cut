import type { Meta, StoryObj } from '@storybook/vue3'
import RentalInquiryForm from './RentalInquiryForm.vue'

const meta: Meta<typeof RentalInquiryForm> = {
  title: 'Content/RentalInquiryForm',
  component: RentalInquiryForm,
  decorators: [
    () => ({
      template: '<div style="max-width: 25rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof RentalInquiryForm>

export const Default: Story = {
  render: () => ({
    components: { RentalInquiryForm },
    template: '<RentalInquiryForm />',
  }),
}
