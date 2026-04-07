import type { Meta, StoryObj } from '@storybook/vue3'
import SkipNav from './SkipNav.vue'

const meta: Meta<typeof SkipNav> = {
  title: 'Layout/SkipNav',
  component: SkipNav,
  parameters: {
    docs: {
      description: {
        component: 'Hidden skip navigation link. Press Tab to reveal it. Click or press Enter to jump to #main-content.',
      },
    },
  },
}

export default meta
type Story = StoryObj<typeof SkipNav>

export const Default: Story = {
  render: () => ({
    components: { SkipNav },
    template: `
      <div>
        <SkipNav />
        <p style="color: var(--tertiary); font-family: var(--font-body); font-size: var(--type-body-md);">
          Press <kbd>Tab</kbd> to reveal the skip navigation link.
        </p>
        <div id="main-content" tabindex="-1" style="margin-top: var(--space-xl); color: var(--on-surface); font-family: var(--font-body);">
          <p>Main content area</p>
        </div>
      </div>
    `,
  }),
}
