import type { Meta, StoryObj } from '@storybook/vue3'
import SidebarNav from './SidebarNav.vue'

const meta: Meta<typeof SidebarNav> = {
  title: 'Layout/SidebarNav',
  component: SidebarNav,
}

export default meta
type Story = StoryObj<typeof SidebarNav>

const accountItems = [
  { label: 'Dashboard', href: '/account', icon: 'home' as const },
  { label: 'Profile', href: '/account/profile', icon: 'account' as const },
  { label: 'Orders', href: '/account/orders', icon: 'orders' as const },
  { label: 'Loyalty', href: '/account/loyalty', icon: 'loyalty' as const },
  { label: 'Bookings', href: '/account/bookings', icon: 'bookings' as const },
  { label: 'Payment', href: '/account/payment-methods', icon: 'payment' as const },
]

export const Desktop: Story = {
  render: () => ({
    components: { SidebarNav },
    setup: () => ({ accountItems }),
    template: `
      <div style="display: flex; gap: var(--space-2xl); min-height: 30rem;">
        <SidebarNav :items="accountItems" />
        <div style="flex: 1; padding: var(--space-lg); color: var(--on-surface); font-family: var(--font-body);">
          <p>Page content area. Sidebar shows at 15rem on desktop, 4rem icon-only on tablet, and collapses to bottom bar on mobile.</p>
        </div>
      </div>
    `,
  }),
}

export const AllItems: Story = {
  render: () => ({
    components: { SidebarNav },
    setup: () => ({ accountItems }),
    template: '<SidebarNav :items="accountItems" />',
  }),
}
