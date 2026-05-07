import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvIconButton from '~/components/ui/CvIconButton.vue'

describe('CvIconButton', () => {
  it('renders as a native button by default', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'chevron-left', label: 'Previous month' },
    })
    expect(wrapper.element.tagName).toBe('BUTTON')
  })

  it('exposes the accessible name via aria-label', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'chevron-right', label: 'Next month' },
    })
    expect(wrapper.attributes('aria-label')).toBe('Next month')
  })

  it('emits click', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'close', label: 'Close' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('suppresses click when disabled', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'close', label: 'Close', disabled: true },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
    expect(wrapper.attributes('aria-disabled')).toBe('true')
  })

  it('renders a NuxtLink when href is provided', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'home', label: 'Home', href: '/' },
    })
    expect(wrapper.element.tagName).not.toBe('BUTTON')
    expect(wrapper.html()).toContain('/')
  })

  it('falls back to button when href is provided but disabled', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'home', label: 'Home', href: '/', disabled: true },
    })
    expect(wrapper.element.tagName).toBe('BUTTON')
  })

  it('applies size modifier class', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'close', label: 'Close', size: 'lg' },
    })
    expect(wrapper.classes()).toContain('cv-icon-button--lg')
  })

  it('renders the requested icon', async () => {
    const wrapper = await mountSuspended(CvIconButton, {
      props: { icon: 'chevron-left', label: 'Prev' },
    })
    expect(wrapper.find('svg').exists()).toBe(true)
  })
})
