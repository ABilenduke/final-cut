import type { Meta, StoryObj } from '@storybook/vue3'
import CvBadge from './CvBadge.vue'

const meta: Meta<typeof CvBadge> = {
  title: 'UI/CvBadge',
  component: CvBadge,
  argTypes: {
    variant: { control: 'select', options: ['default', 'accent', 'warning'] },
    size: { control: 'select', options: ['sm', 'default'] },
  },
}

export default meta
type Story = StoryObj<typeof CvBadge>

export const Default: Story = {
  args: { variant: 'default' },
  render: (args) => ({
    components: { CvBadge },
    setup: () => ({ args }),
    template: '<CvBadge v-bind="args">Drama</CvBadge>',
  }),
}

export const Accent: Story = {
  render: () => ({
    components: { CvBadge },
    template: '<CvBadge variant="accent">8.5</CvBadge>',
  }),
}

export const Warning: Story = {
  render: () => ({
    components: { CvBadge },
    template: '<CvBadge variant="warning">Sold Out</CvBadge>',
  }),
}

export const AllVariants: Story = {
  render: () => ({
    components: { CvBadge },
    template: `
      <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <CvBadge variant="default">Default</CvBadge>
        <CvBadge variant="accent">Accent</CvBadge>
        <CvBadge variant="warning">Warning</CvBadge>
        <CvBadge variant="default" size="sm">Small</CvBadge>
        <CvBadge variant="accent" size="sm">Small Accent</CvBadge>
      </div>
    `,
  }),
}
