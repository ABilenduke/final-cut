import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvButton from '~/components/ui/CvButton.vue'

describe('CvButton', () => {
  it('renders as a button by default', async () => {
    const wrapper = await mountSuspended(CvButton, {
      slots: { default: 'Click me' },
    })
    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.text()).toBe('Click me')
  })

  it('emits click event', async () => {
    const wrapper = await mountSuspended(CvButton, {
      slots: { default: 'Click' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('suppresses click when disabled', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { disabled: true },
      slots: { default: 'Disabled' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('sets aria-disabled when disabled', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { disabled: true },
      slots: { default: 'Disabled' },
    })
    expect(wrapper.attributes('aria-disabled')).toBe('true')
  })

  it('suppresses click when loading', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { loading: true },
      slots: { default: 'Loading' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('sets aria-busy when loading', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { loading: true },
      slots: { default: 'Loading' },
    })
    expect(wrapper.attributes('aria-busy')).toBe('true')
  })

  it('renders as button when href is provided but disabled', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { href: '/test', disabled: true },
      slots: { default: 'Link' },
    })
    expect(wrapper.element.tagName).toBe('BUTTON')
  })

  it('applies variant class', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { variant: 'secondary' },
      slots: { default: 'Secondary' },
    })
    expect(wrapper.classes()).toContain('cv-button--secondary')
  })

  it('applies size class', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { size: 'lg' },
      slots: { default: 'Large' },
    })
    expect(wrapper.classes()).toContain('cv-button--lg')
  })

  it('renders icon-left slot', async () => {
    const wrapper = await mountSuspended(CvButton, {
      slots: {
        default: 'Label',
        'icon-left': '<span class="test-icon">★</span>',
      },
    })
    expect(wrapper.find('.test-icon').exists()).toBe(true)
  })

  it('defaults to type=button', async () => {
    const wrapper = await mountSuspended(CvButton, {
      slots: { default: 'Btn' },
    })
    expect(wrapper.attributes('type')).toBe('button')
  })

  it('accepts type=submit', async () => {
    const wrapper = await mountSuspended(CvButton, {
      props: { type: 'submit' },
      slots: { default: 'Submit' },
    })
    expect(wrapper.attributes('type')).toBe('submit')
  })
})
