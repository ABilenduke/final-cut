import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvChip from '~/components/ui/CvChip.vue'

describe('CvChip', () => {
  it('renders the label', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Showtimes' },
    })
    expect(wrapper.text()).toContain('Showtimes')
  })

  it('starts inactive by default and exposes aria-pressed=false', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Specials' },
    })
    expect(wrapper.attributes('aria-pressed')).toBe('false')
    expect(wrapper.classes()).not.toContain('cv-chip--active')
  })

  it('applies active class and aria-pressed=true when active', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Members', active: true },
    })
    expect(wrapper.classes()).toContain('cv-chip--active')
    expect(wrapper.attributes('aria-pressed')).toBe('true')
  })

  it('emits update:active and toggle on click', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Sensory', active: false },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('toggle')).toHaveLength(1)
    expect(wrapper.emitted('update:active')?.[0]).toEqual([true])
  })

  it('emits update:active with false when toggling off', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Captions', active: true },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('update:active')?.[0]).toEqual([false])
  })

  it('renders a colored dot when color prop provided', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Audio', color: '#FFC9A8' },
    })
    expect(wrapper.find('.cv-chip__dot').exists()).toBe(true)
    expect(wrapper.attributes('style')).toContain('--cv-chip-color: #FFC9A8')
  })

  it('omits the dot when color prop is missing', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'No-color' },
    })
    expect(wrapper.find('.cv-chip__dot').exists()).toBe(false)
  })

  it('applies compact modifier class', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Compact', compact: true },
    })
    expect(wrapper.classes()).toContain('cv-chip--compact')
  })

  it('suppresses click and emits nothing when disabled', async () => {
    const wrapper = await mountSuspended(CvChip, {
      props: { label: 'Off', disabled: true },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('toggle')).toBeUndefined()
    expect(wrapper.emitted('update:active')).toBeUndefined()
    expect(wrapper.classes()).toContain('cv-chip--disabled')
  })
})
