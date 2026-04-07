import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvCard from '~/components/ui/CvCard.vue'

describe('CvCard', () => {
  it('renders with default variant class', async () => {
    const wrapper = await mountSuspended(CvCard, {
      slots: { default: 'Content' },
    })
    expect(wrapper.classes()).toContain('cv-card--default')
  })

  it('renders with low variant class', async () => {
    const wrapper = await mountSuspended(CvCard, {
      props: { variant: 'low' },
      slots: { default: 'Content' },
    })
    expect(wrapper.classes()).toContain('cv-card--low')
  })

  it('renders with high variant class', async () => {
    const wrapper = await mountSuspended(CvCard, {
      props: { variant: 'high' },
      slots: { default: 'Content' },
    })
    expect(wrapper.classes()).toContain('cv-card--high')
  })

  it('adds interactive class when interactive', async () => {
    const wrapper = await mountSuspended(CvCard, {
      props: { interactive: true },
      slots: { default: 'Clickable' },
    })
    expect(wrapper.classes()).toContain('cv-card--interactive')
  })

  it('sets tabindex and role when interactive without href', async () => {
    const wrapper = await mountSuspended(CvCard, {
      props: { interactive: true },
      slots: { default: 'Clickable' },
    })
    expect(wrapper.attributes('tabindex')).toBe('0')
    expect(wrapper.attributes('role')).toBe('button')
  })

  it('emits click when interactive', async () => {
    const wrapper = await mountSuspended(CvCard, {
      props: { interactive: true },
      slots: { default: 'Clickable' },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('handles Enter keydown when interactive without href', async () => {
    const wrapper = await mountSuspended(CvCard, {
      props: { interactive: true },
      slots: { default: 'Clickable' },
    })
    await wrapper.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('renders header slot when provided', async () => {
    const wrapper = await mountSuspended(CvCard, {
      slots: {
        default: 'Body',
        header: 'Header Content',
      },
    })
    expect(wrapper.find('.cv-card__header').exists()).toBe(true)
    expect(wrapper.find('.cv-card__header').text()).toBe('Header Content')
  })

  it('renders footer slot when provided', async () => {
    const wrapper = await mountSuspended(CvCard, {
      slots: {
        default: 'Body',
        footer: 'Footer Content',
      },
    })
    expect(wrapper.find('.cv-card__footer').exists()).toBe(true)
  })
})
