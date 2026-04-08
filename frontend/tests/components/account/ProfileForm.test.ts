import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ProfileForm from '~/components/account/ProfileForm.vue'
import type { UserProfile } from '~/types/user'

function mockProfile(overrides: Partial<UserProfile> = {}): UserProfile {
  return {
    id: '1',
    email: 'test@example.com',
    name: 'Test User',
    avatarUrl: null,
    loyaltyPoints: 100,
    loyaltyTier: 'member',
    premierExpiry: null,
    createdAt: '2026-01-01T00:00:00Z',
    phone: null,
    dateOfBirth: null,
    ...overrides,
  }
}

describe('ProfileForm', () => {
  it('renders avatar with initial from profile name', async () => {
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile: mockProfile(), saving: false },
    })
    const avatar = wrapper.find('.profile-form__avatar-circle')
    expect(avatar.text()).toBe('T')
  })

  it('renders all form fields with profile values', async () => {
    const profile = mockProfile({
      name: 'Jane Doe',
      email: 'jane@example.com',
      phone: '555-1234',
      dateOfBirth: '1990-05-15',
    })
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile, saving: false },
    })

    const inputs = wrapper.findAll('input')
    const nameInput = inputs.find(i => i.attributes('type') !== 'email' && i.attributes('type') !== 'tel' && i.attributes('type') !== 'date' && i.attributes('type') !== 'password')
    const emailInput = inputs.find(i => i.attributes('type') === 'email')
    const phoneInput = inputs.find(i => i.attributes('type') === 'tel')
    const dateInput = inputs.find(i => i.attributes('type') === 'date')

    expect(nameInput).toBeDefined()
    expect((nameInput!.element as HTMLInputElement).value).toBe('Jane Doe')
    expect((emailInput!.element as HTMLInputElement).value).toBe('jane@example.com')
    expect((phoneInput!.element as HTMLInputElement).value).toBe('555-1234')
    expect((dateInput!.element as HTMLInputElement).value).toBe('1990-05-15')
  })

  it('does not emit save if nothing changed', async () => {
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile: mockProfile(), saving: false },
    })
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('save')).toBeUndefined()
  })

  it('emits save with only changed fields', async () => {
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile: mockProfile(), saving: false },
    })

    const nameInput = wrapper.findAll('input').find(
      i => i.attributes('type') !== 'email' && i.attributes('type') !== 'tel' && i.attributes('type') !== 'date' && i.attributes('type') !== 'password',
    )
    await nameInput!.setValue('New Name')
    await wrapper.find('form').trigger('submit')

    const emitted = wrapper.emitted('save')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toEqual({ name: 'New Name' })
  })

  it('shows error when new password entered without current password', async () => {
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile: mockProfile(), saving: false },
    })

    const passwordInputs = wrapper.findAll('input[type="password"]')
    // currentPassword, newPassword, confirmPassword
    await passwordInputs[1].setValue('newpass123')
    await passwordInputs[2].setValue('newpass123')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.text()).toContain('Current password is required')
  })

  it('shows password mismatch error when passwords do not match', async () => {
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile: mockProfile(), saving: false },
    })

    const passwordInputs = wrapper.findAll('input[type="password"]')
    // currentPassword, newPassword, confirmPassword
    await passwordInputs[0].setValue('currentpass')
    await passwordInputs[1].setValue('newpass123')
    await passwordInputs[2].setValue('differentpass')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.text()).toContain('Passwords do not match')
  })

  it('includes password fields in emit with snake_case keys', async () => {
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile: mockProfile(), saving: false },
    })

    const passwordInputs = wrapper.findAll('input[type="password"]')
    await passwordInputs[0].setValue('currentpass')
    await passwordInputs[1].setValue('newpass123')
    await passwordInputs[2].setValue('newpass123')
    await wrapper.find('form').trigger('submit')

    const emitted = wrapper.emitted('save')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toEqual({
      current_password: 'currentpass',
      password: 'newpass123',
      password_confirmation: 'newpass123',
    })
  })

  it('save button shows loading when saving=true', async () => {
    const wrapper = await mountSuspended(ProfileForm, {
      props: { profile: mockProfile(), saving: true },
    })
    const button = wrapper.find('button[type="submit"]')
    expect(button.attributes('aria-busy')).toBe('true')
    expect(button.attributes('disabled')).toBeDefined()
  })
})
