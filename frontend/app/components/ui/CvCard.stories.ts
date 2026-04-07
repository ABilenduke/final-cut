import type { Meta, StoryObj } from '@storybook/vue3'
import CvCard from './CvCard.vue'

const meta: Meta<typeof CvCard> = {
  title: 'UI/CvCard',
  component: CvCard,
  argTypes: {
    variant: { control: 'select', options: ['low', 'default', 'high'] },
    interactive: { control: 'boolean' },
  },
}

export default meta
type Story = StoryObj<typeof CvCard>

export const Default: Story = {
  render: () => ({
    components: { CvCard },
    template: `
      <CvCard style="max-width: 20rem;">
        <p style="color: var(--on-surface); margin: 0;">Card content on the default surface tier.</p>
      </CvCard>
    `,
  }),
}

export const SurfaceTiers: Story = {
  render: () => ({
    components: { CvCard },
    template: `
      <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
        <CvCard variant="low" style="max-width: 15rem;">
          <p style="color: var(--on-surface); margin: 0;">Low tier</p>
        </CvCard>
        <CvCard variant="default" style="max-width: 15rem;">
          <p style="color: var(--on-surface); margin: 0;">Default tier</p>
        </CvCard>
        <CvCard variant="high" style="max-width: 15rem;">
          <p style="color: var(--on-surface); margin: 0;">High tier</p>
        </CvCard>
      </div>
    `,
  }),
}

export const Interactive: Story = {
  render: () => ({
    components: { CvCard },
    template: `
      <CvCard interactive variant="low" style="max-width: 20rem;">
        <p style="color: var(--on-surface); margin: 0;">Hover me to see the lift effect.</p>
      </CvCard>
    `,
  }),
}

export const WithSlots: Story = {
  render: () => ({
    components: { CvCard },
    template: `
      <CvCard style="max-width: 20rem;">
        <template #header>
          <h3 style="color: var(--on-surface); margin: 0; font-family: var(--font-display); font-size: var(--type-headline-sm);">Card Title</h3>
        </template>
        <p style="color: var(--tertiary); margin: 0;">Card body content with header and footer slots.</p>
        <template #footer>
          <span style="color: var(--on-tertiary-fixed-variant); font-size: var(--type-label-md);">Footer info</span>
        </template>
      </CvCard>
    `,
  }),
}

export const AsLink: Story = {
  render: () => ({
    components: { CvCard },
    template: `
      <CvCard href="/movies/inception" style="max-width: 20rem;">
        <p style="color: var(--on-surface); margin: 0;">Click to navigate (NuxtLink).</p>
      </CvCard>
    `,
  }),
}
