import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CareersPage from '~/pages/careers.vue'
import AccessibilityPage from '~/pages/accessibility.vue'

describe('Careers Page', () => {
  it('renders page title', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.find('.careers-page__title').text()).toBe('Careers')
  })

  it('renders intro text', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.find('.careers-page__intro').text()).toContain('people who care about the details')
  })

  it('renders job openings', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.text()).toContain('Projectionist')
    expect(wrapper.text()).toContain('Front of House Team Member')
    expect(wrapper.text()).toContain('Kitchen & Bar Staff')
  })

  it('renders department and type badges for each opening', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.text()).toContain('Operations')
    expect(wrapper.text()).toContain('Full-time')
    expect(wrapper.text()).toContain('Guest Services')
    expect(wrapper.text()).toContain('Part-time')
  })

  it('renders benefits list', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.text()).toContain('Free movie tickets')
    expect(wrapper.text()).toContain('Flexible scheduling')
  })

  it('renders application instructions with email link', async () => {
    const wrapper = await mountSuspended(CareersPage)
    const link = wrapper.find('a[href="mailto:careers@finalcut.test"]')
    expect(link.exists()).toBe(true)
  })
})

describe('Accessibility Page', () => {
  it('renders page title', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    expect(wrapper.find('.accessibility-page__title').text()).toBe('Accessibility')
  })

  it('renders commitment statement', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    expect(wrapper.find('.accessibility-page__intro').text()).toContain('inclusive experience')
  })

  it('renders all accessibility sections', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    const headings = wrapper.findAll('.accessibility-page__heading')
    const headingTexts = headings.map(h => h.text())

    expect(headingTexts).toContain('Assisted Listening Devices')
    expect(headingTexts).toContain('Wheelchair Seating')
    expect(headingTexts).toContain('Open Caption Showtimes')
    expect(headingTexts).toContain('Audio Description')
    expect(headingTexts).toContain('Sensory-Friendly Screenings')
    expect(headingTexts).toContain('Service Animals')
    expect(headingTexts).toContain('Need Assistance?')
  })

  it('links to open caption calendar filter', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    const link = wrapper.find('a[href="/whats-on?accessibility=open_caption"]')
    expect(link.exists()).toBe(true)
  })

  it('links to audio described calendar filter', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    const link = wrapper.find('a[href="/whats-on?accessibility=audio_described"]')
    expect(link.exists()).toBe(true)
  })

  it('links to sensory friendly calendar filter', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    const link = wrapper.find('a[href="/whats-on?accessibility=sensory_friendly"]')
    expect(link.exists()).toBe(true)
  })

  it('renders contact information', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    expect(wrapper.find('a[href="mailto:accessibility@finalcut.test"]').exists()).toBe(true)
    expect(wrapper.find('a[href="tel:+12125550199"]').exists()).toBe(true)
  })
})
