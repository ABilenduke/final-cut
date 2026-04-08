import type { Meta, StoryObj } from '@storybook/vue3'
import type { UserProfile } from '~/types/user'
import ProfileForm from './ProfileForm.vue'

function mockProfile(overrides: Partial<UserProfile> = {}): UserProfile {
  return {
    id: '1',
    email: 'john@example.com',
    name: 'John Doe',
    avatarUrl: null,
    loyaltyPoints: 500,
    loyaltyTier: 'member',
    premierExpiry: null,
    createdAt: '2026-01-01T00:00:00Z',
    phone: null,
    dateOfBirth: null,
    ...overrides,
  }
}

const meta: Meta<typeof ProfileForm> = {
  title: 'Account/ProfileForm',
  component: ProfileForm,
  decorators: [
    () => ({
      template: '<div style="max-width: 30rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof ProfileForm>

export const Default: Story = {
  render: () => ({
    components: { ProfileForm },
    setup: () => ({ profile: mockProfile() }),
    template: '<ProfileForm :profile="profile" :saving="false" />',
  }),
}

export const Prefilled: Story = {
  render: () => ({
    components: { ProfileForm },
    setup: () => ({
      profile: mockProfile({
        phone: '+1 555-123-4567',
        dateOfBirth: '1990-05-15',
      }),
    }),
    template: '<ProfileForm :profile="profile" :saving="false" />',
  }),
}

export const Saving: Story = {
  render: () => ({
    components: { ProfileForm },
    setup: () => ({ profile: mockProfile() }),
    template: '<ProfileForm :profile="profile" :saving="true" />',
  }),
}
