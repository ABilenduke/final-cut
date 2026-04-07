import type { Meta, StoryObj } from '@storybook/vue3'
import CvSelect from './CvSelect.vue'

const meta: Meta<typeof CvSelect> = {
  title: 'UI/CvSelect',
  component: CvSelect,
  argTypes: {
    disabled: { control: 'boolean' },
    required: { control: 'boolean' },
  },
}

export default meta
type Story = StoryObj<typeof CvSelect>

const sampleOptions = [
  { value: 'birthday', label: 'Birthday Party' },
  { value: 'corporate', label: 'Corporate Event' },
  { value: 'proposal', label: 'Proposal' },
  { value: 'custom', label: 'Custom Event' },
]

export const Default: Story = {
  render: () => ({
    components: { CvSelect },
    data: () => ({ value: '', options: sampleOptions }),
    template: `
      <div style="max-width: 20rem;">
        <CvSelect v-model="value" :options="options" label="Event Type" />
      </div>
    `,
  }),
}

export const WithError: Story = {
  render: () => ({
    components: { CvSelect },
    data: () => ({ value: '', options: sampleOptions }),
    template: `
      <div style="max-width: 20rem;">
        <CvSelect v-model="value" :options="options" label="Event Type" error="Please select an event type" required />
      </div>
    `,
  }),
}

export const Disabled: Story = {
  render: () => ({
    components: { CvSelect },
    data: () => ({ value: 'corporate', options: sampleOptions }),
    template: `
      <div style="max-width: 20rem;">
        <CvSelect v-model="value" :options="options" label="Event Type" disabled />
      </div>
    `,
  }),
}
