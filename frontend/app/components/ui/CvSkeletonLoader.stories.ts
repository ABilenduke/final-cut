import type { Meta, StoryObj } from '@storybook/vue3'
import CvSkeletonLoader from './CvSkeletonLoader.vue'

const meta: Meta<typeof CvSkeletonLoader> = {
  title: 'UI/CvSkeletonLoader',
  component: CvSkeletonLoader,
  argTypes: {
    variant: { control: 'select', options: ['text', 'card', 'image', 'circle'] },
    lines: { control: 'number' },
    width: { control: 'text' },
    height: { control: 'text' },
  },
}

export default meta
type Story = StoryObj<typeof CvSkeletonLoader>

export const TextSingleLine: Story = {
  render: () => ({
    components: { CvSkeletonLoader },
    template: `
      <div style="max-width: 20rem;">
        <CvSkeletonLoader variant="text" />
      </div>
    `,
  }),
}

export const TextMultiLine: Story = {
  render: () => ({
    components: { CvSkeletonLoader },
    template: `
      <div style="max-width: 20rem;">
        <CvSkeletonLoader variant="text" :lines="4" />
      </div>
    `,
  }),
}

export const Card: Story = {
  render: () => ({
    components: { CvSkeletonLoader },
    template: `
      <div style="max-width: 20rem;">
        <CvSkeletonLoader variant="card" />
      </div>
    `,
  }),
}

export const Circle: Story = {
  render: () => ({
    components: { CvSkeletonLoader },
    template: '<CvSkeletonLoader variant="circle" height="5rem" />',
  }),
}

export const AllVariants: Story = {
  render: () => ({
    components: { CvSkeletonLoader },
    template: `
      <div style="display: flex; gap: 2rem; flex-wrap: wrap; align-items: flex-start;">
        <div style="width: 15rem;">
          <p style="color: var(--tertiary); margin: 0 0 0.5rem;">Text (3 lines)</p>
          <CvSkeletonLoader variant="text" :lines="3" />
        </div>
        <div style="width: 15rem;">
          <p style="color: var(--tertiary); margin: 0 0 0.5rem;">Card</p>
          <CvSkeletonLoader variant="card" />
        </div>
        <div>
          <p style="color: var(--tertiary); margin: 0 0 0.5rem;">Circle</p>
          <CvSkeletonLoader variant="circle" height="5rem" />
        </div>
        <div style="width: 15rem;">
          <p style="color: var(--tertiary); margin: 0 0 0.5rem;">Image</p>
          <CvSkeletonLoader variant="image" height="8rem" />
        </div>
      </div>
    `,
  }),
}
