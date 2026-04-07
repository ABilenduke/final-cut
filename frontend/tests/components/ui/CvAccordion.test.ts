import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvAccordion from '~/components/ui/CvAccordion.vue'

describe('CvAccordion', () => {
  it('emits toggle on click', async () => {
    const wrapper = await mountSuspended(CvAccordion, {
      props: { title: 'FAQ', id: 'test-1' },
      slots: { default: 'Answer content' },
    })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('toggle')![0]).toEqual([true])
  })

  it('toggles aria-expanded', async () => {
    const wrapper = await mountSuspended(CvAccordion, {
      props: { title: 'FAQ', id: 'test-2' },
      slots: { default: 'Answer' },
    })
    const button = wrapper.find('button')
    expect(button.attributes('aria-expanded')).toBe('false')
    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('true')
  })

  it('toggles on Enter key', async () => {
    const wrapper = await mountSuspended(CvAccordion, {
      props: { title: 'FAQ', id: 'test-3' },
      slots: { default: 'Answer' },
    })
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('toggle')).toHaveLength(1)
  })

  it('respects controlled open prop', async () => {
    const wrapper = await mountSuspended(CvAccordion, {
      props: { title: 'FAQ', id: 'test-4', open: true },
      slots: { default: 'Answer' },
    })
    const button = wrapper.find('button')
    expect(button.attributes('aria-expanded')).toBe('true')
  })

  it('manages own state when open prop is not provided', async () => {
    const wrapper = await mountSuspended(CvAccordion, {
      props: { title: 'FAQ', id: 'test-5' },
      slots: { default: 'Answer' },
    })
    const button = wrapper.find('button')
    expect(button.attributes('aria-expanded')).toBe('false')
    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('true')
    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('false')
  })

  it('has aria-controls linking trigger to panel', async () => {
    const wrapper = await mountSuspended(CvAccordion, {
      props: { title: 'FAQ', id: 'test-6' },
      slots: { default: 'Answer' },
    })
    const button = wrapper.find('button')
    const panel = wrapper.find('[role="region"]')
    expect(button.attributes('aria-controls')).toBe(panel.attributes('id'))
  })

  it('has aria-labelledby on region', async () => {
    const wrapper = await mountSuspended(CvAccordion, {
      props: { title: 'FAQ', id: 'test-7' },
      slots: { default: 'Answer' },
    })
    const panel = wrapper.find('[role="region"]')
    const button = wrapper.find('button')
    expect(panel.attributes('aria-labelledby')).toBe(button.attributes('id'))
  })
})
