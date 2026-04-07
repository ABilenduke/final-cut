import type { Meta, StoryObj } from '@storybook/vue3'
import CvModal from './CvModal.vue'

const meta: Meta<typeof CvModal> = {
  title: 'UI/CvModal',
  component: CvModal,
  argTypes: {
    size: { control: 'select', options: ['sm', 'default', 'lg'] },
    closeable: { control: 'boolean' },
  },
}

export default meta
type Story = StoryObj<typeof CvModal>

export const Default: Story = {
  render: () => ({
    components: { CvModal },
    data: () => ({ open: false }),
    template: `
      <div>
        <button @click="open = true" style="background: var(--primary-container); color: var(--secondary); border: none; padding: 0.75rem 1rem; cursor: pointer; border-radius: 0.125rem;">Open Modal</button>
        <CvModal v-model="open" title="Confirm Booking">
          <p style="color: var(--tertiary); margin: 0;">Are you sure you want to confirm this booking? This action cannot be undone.</p>
          <template #footer>
            <button @click="open = false" style="background: var(--surface-container); color: var(--on-surface); border: none; padding: 0.75rem 1rem; cursor: pointer;">Cancel</button>
            <button @click="open = false" style="background: var(--primary-container); color: var(--secondary); border: none; padding: 0.75rem 1rem; cursor: pointer;">Confirm</button>
          </template>
        </CvModal>
      </div>
    `,
  }),
}

export const Small: Story = {
  render: () => ({
    components: { CvModal },
    data: () => ({ open: false }),
    template: `
      <div>
        <button @click="open = true" style="background: var(--primary-container); color: var(--secondary); border: none; padding: 0.75rem 1rem; cursor: pointer; border-radius: 0.125rem;">Small Modal</button>
        <CvModal v-model="open" title="Delete Item" size="sm">
          <p style="color: var(--tertiary); margin: 0;">This will permanently remove the item.</p>
          <template #footer>
            <button @click="open = false" style="background: var(--primary-container); color: var(--secondary); border: none; padding: 0.75rem 1rem; cursor: pointer;">Delete</button>
          </template>
        </CvModal>
      </div>
    `,
  }),
}

export const Large: Story = {
  render: () => ({
    components: { CvModal },
    data: () => ({ open: false }),
    template: `
      <div>
        <button @click="open = true" style="background: var(--primary-container); color: var(--secondary); border: none; padding: 0.75rem 1rem; cursor: pointer; border-radius: 0.125rem;">Large Modal</button>
        <CvModal v-model="open" title="Terms and Conditions" size="lg">
          <div style="color: var(--tertiary);">
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
            <p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
            <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>
          </div>
        </CvModal>
      </div>
    `,
  }),
}

export const NotCloseable: Story = {
  render: () => ({
    components: { CvModal },
    data: () => ({ open: false }),
    template: `
      <div>
        <button @click="open = true" style="background: var(--primary-container); color: var(--secondary); border: none; padding: 0.75rem 1rem; cursor: pointer; border-radius: 0.125rem;">Non-closeable Modal</button>
        <CvModal v-model="open" title="Processing Payment" :closeable="false">
          <p style="color: var(--tertiary); margin: 0;">Please wait while we process your payment...</p>
          <template #footer>
            <button @click="open = false" style="background: var(--surface-container); color: var(--on-surface); border: none; padding: 0.75rem 1rem; cursor: pointer;">Force Close (demo only)</button>
          </template>
        </CvModal>
      </div>
    `,
  }),
}
