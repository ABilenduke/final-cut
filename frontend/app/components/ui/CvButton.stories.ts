import type { Meta, StoryObj } from '@storybook/vue3'
import CvButton from './CvButton.vue'

const meta: Meta<typeof CvButton> = {
  title: 'UI/CvButton',
  component: CvButton,
  argTypes: {
    variant: { control: 'select', options: ['primary', 'secondary', 'tertiary'] },
    size: { control: 'select', options: ['sm', 'default', 'lg'] },
    disabled: { control: 'boolean' },
    loading: { control: 'boolean' },
  },
}

export default meta
type Story = StoryObj<typeof CvButton>

export const Primary: Story = {
  render: (args) => ({
    components: { CvButton },
    setup: () => ({ args }),
    template: '<CvButton v-bind="args">Get Tickets</CvButton>',
  }),
  args: { variant: 'primary' },
}

export const Secondary: Story = {
  render: (args) => ({
    components: { CvButton },
    setup: () => ({ args }),
    template: '<CvButton v-bind="args">Cancel</CvButton>',
  }),
  args: { variant: 'secondary' },
}

export const Tertiary: Story = {
  render: (args) => ({
    components: { CvButton },
    setup: () => ({ args }),
    template: '<CvButton v-bind="args">Learn More</CvButton>',
  }),
  args: { variant: 'tertiary' },
}

export const AllVariants: Story = {
  render: () => ({
    components: { CvButton },
    template: `
      <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
        <CvButton variant="primary">Primary</CvButton>
        <CvButton variant="secondary">Secondary</CvButton>
        <CvButton variant="tertiary">Tertiary</CvButton>
      </div>
    `,
  }),
}

export const Sizes: Story = {
  render: () => ({
    components: { CvButton },
    template: `
      <div style="display: flex; gap: 1.5rem; align-items: center;">
        <CvButton size="sm">Small</CvButton>
        <CvButton size="default">Default</CvButton>
        <CvButton size="lg">Large</CvButton>
      </div>
    `,
  }),
}

export const Loading: Story = {
  render: () => ({
    components: { CvButton },
    template: `
      <div style="display: flex; gap: 1.5rem; align-items: center;">
        <CvButton loading>Processing...</CvButton>
        <CvButton variant="secondary" loading>Loading</CvButton>
      </div>
    `,
  }),
}

export const Disabled: Story = {
  render: () => ({
    components: { CvButton },
    template: `
      <div style="display: flex; gap: 1.5rem; align-items: center;">
        <CvButton disabled>Unavailable</CvButton>
        <CvButton variant="secondary" disabled>Disabled</CvButton>
        <CvButton variant="tertiary" disabled>Disabled</CvButton>
      </div>
    `,
  }),
}

export const AsLink: Story = {
  render: () => ({
    components: { CvButton },
    template: '<CvButton href="/movies">Browse Movies</CvButton>',
  }),
}
