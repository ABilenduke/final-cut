import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import QuickShowtimeStrip from '~/components/home/QuickShowtimeStrip.vue'
import type { Location } from '~/types/location'

const mockLocation: Location = {
  id: '1',
  name: 'Downtown Theatre',
  slug: 'downtown-theatre',
  address: '123 Main St',
}

describe('QuickShowtimeStrip', () => {
  it('shows location name when provided', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: mockLocation },
    })
    expect(wrapper.text()).toContain('Downtown Theatre')
  })

  it('shows "Change" link', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: mockLocation },
    })
    expect(wrapper.text()).toContain('Change')
  })

  it('emits change-location on Change click', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: mockLocation },
    })
    const changeButton = wrapper.find('.showtime-strip__change')
    await changeButton.trigger('click')
    expect(wrapper.emitted('change-location')).toHaveLength(1)
  })

  it('shows date pills', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: mockLocation },
    })
    expect(wrapper.text()).toContain('Today')
    expect(wrapper.text()).toContain('Tomorrow')
    expect(wrapper.text()).toContain('This Weekend')
  })

  it('Today is active by default', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: mockLocation },
    })
    const pills = wrapper.findAll('.showtime-strip__pill')
    const todayPill = pills.find(p => p.text() === 'Today')
    expect(todayPill).toBeDefined()
    expect(todayPill!.attributes('aria-pressed')).toBe('true')
    expect(todayPill!.classes()).toContain('showtime-strip__pill--active')
  })

  it('shows Browse Showtimes CTA', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: mockLocation },
    })
    expect(wrapper.text()).toContain('Browse Showtimes')
  })

  it('no-location state shows prompt', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: null },
    })
    expect(wrapper.text()).toContain('Select a location to see showtimes')
  })

  it('no-location state shows Choose Location button', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: null },
    })
    expect(wrapper.text()).toContain('Choose Location')
  })

  it('no-location emits change-location on Choose Location click', async () => {
    const wrapper = await mountSuspended(QuickShowtimeStrip, {
      props: { location: null },
    })
    const button = wrapper.find('cvbutton')
    await button.trigger('click')
    expect(wrapper.emitted('change-location')).toHaveLength(1)
  })
})
