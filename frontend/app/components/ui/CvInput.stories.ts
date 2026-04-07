import type { Meta, StoryObj } from '@storybook/vue3'
import CvInput from './CvInput.vue'

const meta: Meta<typeof CvInput> = {
  title: 'UI/CvInput',
  component: CvInput,
  argTypes: {
    type: { control: 'select', options: ['text', 'email', 'password', 'number', 'tel', 'search'] },
    size: { control: 'select', options: ['sm', 'default'] },
    disabled: { control: 'boolean' },
    required: { control: 'boolean' },
  },
}

export default meta
type Story = StoryObj<typeof CvInput>

export const Default: Story = {
  render: () => ({
    components: { CvInput },
    data: () => ({ value: '' }),
    template: `
      <div style="max-width: 20rem;">
        <CvInput v-model="value" label="Email" type="email" placeholder="you@example.com" />
      </div>
    `,
  }),
}

export const WithError: Story = {
  render: () => ({
    components: { CvInput },
    data: () => ({ value: 'bad@' }),
    template: `
      <div style="max-width: 20rem;">
        <CvInput v-model="value" label="Email" type="email" error="Please enter a valid email address" />
      </div>
    `,
  }),
}

export const WithHelpText: Story = {
  render: () => ({
    components: { CvInput },
    data: () => ({ value: '' }),
    template: `
      <div style="max-width: 20rem;">
        <CvInput v-model="value" label="Password" type="password" help-text="Must be at least 8 characters" required />
      </div>
    `,
  }),
}

export const Disabled: Story = {
  render: () => ({
    components: { CvInput },
    data: () => ({ value: 'Cannot edit' }),
    template: `
      <div style="max-width: 20rem;">
        <CvInput v-model="value" label="Name" disabled />
      </div>
    `,
  }),
}

export const AllStates: Story = {
  render: () => ({
    components: { CvInput },
    data: () => ({ v1: '', v2: '', v3: 'Jane', v4: '' }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 2rem; max-width: 20rem;">
        <CvInput v-model="v1" label="Default" placeholder="Type here..." />
        <CvInput v-model="v2" label="Required" required placeholder="Required field" />
        <CvInput v-model="v3" label="With Error" error="This field has an error" />
        <CvInput v-model="v4" label="Disabled" disabled placeholder="Cannot interact" />
      </div>
    `,
  }),
}
