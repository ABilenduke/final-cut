import type { Meta, StoryObj } from '@storybook/vue3'
import SiteHeader from './SiteHeader.vue'

const meta: Meta<typeof SiteHeader> = {
  title: 'Layout/SiteHeader',
  component: SiteHeader,
  parameters: {
    layout: 'fullscreen',
  },
}

export default meta
type Story = StoryObj<typeof SiteHeader>

export const Default: Story = {
  render: () => ({
    components: { SiteHeader },
    template: `
      <div>
        <SiteHeader />
        <div style="padding-top: 5rem; padding-left: var(--space-lg); color: var(--on-surface); font-family: var(--font-body);">
          <p>Page content below the header</p>
        </div>
      </div>
    `,
  }),
}

export const Authenticated: Story = {
  render: () => ({
    components: { SiteHeader },
    setup() {
      const { user } = useAuth()
      user.value = { id: '1', email: 'viewer@finalcut.test', name: 'Alex', avatarUrl: null, loyaltyPoints: 1200, loyaltyTier: 'member', premierExpiry: null, createdAt: '2026-01-01T00:00:00Z' }
      return {}
    },
    template: `
      <div>
        <SiteHeader />
        <div style="padding-top: 5rem; padding-left: var(--space-lg); color: var(--on-surface); font-family: var(--font-body);">
          <p>Authenticated view — avatar icon shows in desktop nav</p>
        </div>
      </div>
    `,
  }),
}

export const WithLocations: Story = {
  render: () => ({
    components: { SiteHeader },
    setup() {
      const { locations, activeLocation } = useLocations()
      locations.value = [
        { id: '1', name: 'Downtown', slug: 'downtown', address: '123 Main St' },
        { id: '2', name: 'Westside', slug: 'westside', address: '456 West Ave' },
      ]
      activeLocation.value = locations.value[0] ?? null
      return {}
    },
    template: `
      <div>
        <SiteHeader />
        <div style="padding-top: 5rem; padding-left: var(--space-lg); color: var(--on-surface); font-family: var(--font-body);">
          <p>Location switcher visible in header</p>
        </div>
      </div>
    `,
  }),
}
