import type { Meta, StoryObj } from '@storybook/vue3'
import MobileNav from './MobileNav.vue'

const meta: Meta<typeof MobileNav> = {
  title: 'Layout/MobileNav',
  component: MobileNav,
  parameters: {
    layout: 'fullscreen',
    viewport: { defaultViewport: 'mobile1' },
  },
}

export default meta
type Story = StoryObj<typeof MobileNav>

export const Default: Story = {
  render: () => ({
    components: { MobileNav },
    template: `
      <div>
        <div style="padding: var(--space-lg); padding-bottom: 5rem; color: var(--on-surface); font-family: var(--font-body);">
          <p>Page content above the mobile nav. Resize viewport below 60rem to see it.</p>
        </div>
        <MobileNav />
      </div>
    `,
  }),
}
