import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvFormField from '~/components/ui/_internal/CvFormField.vue'

describe('CvFormField', () => {
  it('renders label with for attribute', async () => {
    const wrapper = await mountSuspended(CvFormField, {
      props: { label: 'Email' },
      slots: {
        default: ({ id }: { id: string }) => `<input id="${id}" />`,
      },
    })
    const label = wrapper.find('label')
    expect(label.exists()).toBe(true)
    expect(label.text()).toContain('Email')
    const forAttr = label.attributes('for')
    expect(forAttr).toBeTruthy()
  })

  it('shows required marker when required', async () => {
    const wrapper = await mountSuspended(CvFormField, {
      props: { label: 'Name', required: true },
      slots: { default: () => '<input />' },
    })
    expect(wrapper.find('.cv-form-field__required').exists()).toBe(true)
    expect(wrapper.find('.cv-form-field__required').text()).toBe('*')
  })

  it('renders error message with correct id', async () => {
    const wrapper = await mountSuspended(CvFormField, {
      props: { label: 'Email', error: 'Invalid email' },
      slots: { default: () => '<input />' },
    })
    const errorEl = wrapper.find('.cv-form-field__error')
    expect(errorEl.exists()).toBe(true)
    expect(errorEl.text()).toBe('Invalid email')
    expect(errorEl.attributes('id')).toContain('-error')
    expect(errorEl.attributes('aria-live')).toBe('polite')
  })

  it('renders help text', async () => {
    const wrapper = await mountSuspended(CvFormField, {
      props: { label: 'Password', helpText: 'Must be 8 characters' },
      slots: { default: () => '<input />' },
    })
    const helpEl = wrapper.find('.cv-form-field__help')
    expect(helpEl.exists()).toBe(true)
    expect(helpEl.text()).toBe('Must be 8 characters')
  })

  it('hides help text when error is shown', async () => {
    const wrapper = await mountSuspended(CvFormField, {
      props: { label: 'Password', helpText: 'Help', error: 'Error' },
      slots: { default: () => '<input />' },
    })
    expect(wrapper.find('.cv-form-field__help').exists()).toBe(false)
    expect(wrapper.find('.cv-form-field__error').exists()).toBe(true)
  })

  it('applies disabled class', async () => {
    const wrapper = await mountSuspended(CvFormField, {
      props: { label: 'Name', disabled: true },
      slots: { default: () => '<input />' },
    })
    expect(wrapper.find('.cv-form-field--disabled').exists()).toBe(true)
  })

  it('applies inline class when inline prop is set', async () => {
    const wrapper = await mountSuspended(CvFormField, {
      props: { label: 'Check me', inline: true },
      slots: { default: () => '<input type="checkbox" />' },
    })
    expect(wrapper.find('.cv-form-field--inline').exists()).toBe(true)
  })
})
