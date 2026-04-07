import type { Meta, StoryObj } from '@storybook/vue3'
import CvCheckbox from './CvCheckbox.vue'

const meta: Meta<typeof CvCheckbox> = {
  title: 'UI/CvCheckbox',
  component: CvCheckbox,
  argTypes: {
    disabled: { control: 'boolean' },
    required: { control: 'boolean' },
  },
}

export default meta
type Story = StoryObj<typeof CvCheckbox>

export const Default: Story = {
  render: () => ({
    components: { CvCheckbox },
    data: () => ({ checked: false }),
    template: `
      <CvCheckbox v-model="checked" label="I agree to the terms and conditions" />
    `,
  }),
}

export const Checked: Story = {
  render: () => ({
    components: { CvCheckbox },
    data: () => ({ checked: true }),
    template: `
      <CvCheckbox v-model="checked" label="Remember me" />
    `,
  }),
}

export const WithError: Story = {
  render: () => ({
    components: { CvCheckbox },
    data: () => ({ checked: false }),
    template: `
      <CvCheckbox v-model="checked" label="I accept the privacy policy" error="You must accept to continue" required />
    `,
  }),
}

export const Disabled: Story = {
  render: () => ({
    components: { CvCheckbox },
    data: () => ({ checked: false }),
    template: `
      <CvCheckbox v-model="checked" label="This option is unavailable" disabled />
    `,
  }),
}

export const FilterGroup: Story = {
  render: () => ({
    components: { CvCheckbox },
    data: () => ({ sensory: true, captions: false, audio: false }),
    template: `
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <CvCheckbox v-model="sensory" label="Sensory Friendly" />
        <CvCheckbox v-model="captions" label="Open Captions" />
        <CvCheckbox v-model="audio" label="Audio Described" />
      </div>
    `,
  }),
}
