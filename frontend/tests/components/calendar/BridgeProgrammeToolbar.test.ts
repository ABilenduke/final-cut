import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeProgrammeToolbar from '~/components/calendar/BridgeProgrammeToolbar.vue'

const baseProps = {
  monthLabel: 'May',
  year: 2026,
  view: 'month' as const,
  todayLabel: '6 May',
}

describe('BridgeProgrammeToolbar', () => {
  it('renders the eyebrow + h1 with italicized "On"', async () => {
    const wrapper = await mountSuspended(BridgeProgrammeToolbar, { props: baseProps })
    const title = wrapper.find('.bridge-toolbar__title')
    expect(title.text()).toContain("What's")
    expect(title.text()).toContain('On')
    expect(title.text()).toContain('May')
    expect(title.text()).toContain('2026')
    expect(title.html()).toContain('<em>On</em>')
  })

  it('renders the volume label in the eyebrow', async () => {
    const wrapper = await mountSuspended(BridgeProgrammeToolbar, {
      props: { ...baseProps, volumeLabel: 'Programme · Vol XXIII' },
    })
    expect(wrapper.find('.bridge-toolbar__eyebrow').text()).toContain('Programme · Vol XXIII')
  })

  it('renders the today button with the supplied label', async () => {
    const wrapper = await mountSuspended(BridgeProgrammeToolbar, { props: baseProps })
    expect(wrapper.find('.bridge-toolbar__today').text()).toContain('Today · 6 May')
  })

  it('emits today on the today button click', async () => {
    const wrapper = await mountSuspended(BridgeProgrammeToolbar, { props: baseProps })
    await wrapper.find('.bridge-toolbar__today').trigger('click')
    expect(wrapper.emitted('today')).toHaveLength(1)
  })

  it('emits prev when the prev icon button is clicked', async () => {
    const wrapper = await mountSuspended(BridgeProgrammeToolbar, { props: baseProps })
    const prev = wrapper.find('[aria-label="Previous month"]')
    await prev.trigger('click')
    expect(wrapper.emitted('prev')).toHaveLength(1)
  })

  it('emits next when the next icon button is clicked', async () => {
    const wrapper = await mountSuspended(BridgeProgrammeToolbar, { props: baseProps })
    const next = wrapper.find('[aria-label="Next month"]')
    await next.trigger('click')
    expect(wrapper.emitted('next')).toHaveLength(1)
  })

  it('renders Week and List as disabled in the segmented control', async () => {
    const wrapper = await mountSuspended(BridgeProgrammeToolbar, { props: baseProps })
    const buttons = wrapper.findAll('.cv-segmented__btn')
    const week = buttons.find((b) => b.text() === 'Week')!
    const list = buttons.find((b) => b.text() === 'List')!
    expect(week.classes()).toContain('cv-segmented__btn--disabled')
    expect(list.classes()).toContain('cv-segmented__btn--disabled')
  })
})
