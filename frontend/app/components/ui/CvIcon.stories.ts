import type { Meta, StoryObj } from '@storybook/vue3'
import CvIcon from './CvIcon.vue'

const meta: Meta<typeof CvIcon> = {
  title: 'UI/CvIcon',
  component: CvIcon,
  argTypes: {
    name: {
      control: 'select',
      options: ['close', 'check', 'chevron-down', 'chevron-up', 'chevron-left', 'chevron-right', 'alert', 'info', 'spinner'],
    },
    size: { control: 'select', options: ['sm', 'md', 'lg', 'xl'] },
    label: { control: 'text' },
  },
}

export default meta
type Story = StoryObj<typeof CvIcon>

export const Default: Story = {
  args: { name: 'check', size: 'md' },
}

export const AllIcons: Story = {
  render: () => ({
    components: { CvIcon },
    template: `
      <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
        <div v-for="name in ['close', 'check', 'chevron-down', 'chevron-up', 'chevron-left', 'chevron-right', 'alert', 'info', 'spinner']" :key="name" style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
          <CvIcon :name="name" size="md" />
          <span style="font-size: 0.75rem; color: var(--tertiary);">{{ name }}</span>
        </div>
      </div>
    `,
  }),
}

export const Sizes: Story = {
  render: () => ({
    components: { CvIcon },
    template: `
      <div style="display: flex; gap: 2rem; align-items: center;">
        <div v-for="size in ['sm', 'md', 'lg', 'xl']" :key="size" style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
          <CvIcon name="info" :size="size" />
          <span style="font-size: 0.75rem; color: var(--tertiary);">{{ size }}</span>
        </div>
      </div>
    `,
  }),
}

export const Accessible: Story = {
  args: { name: 'alert', size: 'md', label: 'Warning indicator' },
}
