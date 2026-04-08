import type { Meta, StoryObj } from '@storybook/vue3'
import ContactForm from './ContactForm.vue'

const meta: Meta<typeof ContactForm> = {
  title: 'Content/ContactForm',
  component: ContactForm,
  decorators: [
    () => ({
      template: '<div style="max-width: 25rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof ContactForm>

export const Default: Story = {
  render: () => ({
    components: { ContactForm },
    template: '<ContactForm />',
  }),
}
