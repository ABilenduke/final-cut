import type { Meta, StoryObj } from '@storybook/vue3'
import CvTextarea from './CvTextarea.vue'

const meta: Meta<typeof CvTextarea> = {
  title: 'UI/CvTextarea',
  component: CvTextarea,
  argTypes: {
    rows: { control: 'number' },
    disabled: { control: 'boolean' },
    required: { control: 'boolean' },
  },
}

export default meta
type Story = StoryObj<typeof CvTextarea>

export const Default: Story = {
  render: () => ({
    components: { CvTextarea },
    data: () => ({ value: '' }),
    template: `
      <div style="max-width: 20rem;">
        <CvTextarea v-model="value" label="Message" placeholder="Tell us about your event..." />
      </div>
    `,
  }),
}

export const WithError: Story = {
  render: () => ({
    components: { CvTextarea },
    data: () => ({ value: '' }),
    template: `
      <div style="max-width: 20rem;">
        <CvTextarea v-model="value" label="Message" error="Message is required" required />
      </div>
    `,
  }),
}

export const Disabled: Story = {
  render: () => ({
    components: { CvTextarea },
    data: () => ({ value: 'Cannot edit this content' }),
    template: `
      <div style="max-width: 20rem;">
        <CvTextarea v-model="value" label="Notes" disabled />
      </div>
    `,
  }),
}
