import { describe, it, expect, beforeEach, vi } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CareersPage from '~/pages/careers.vue'
import FaqPage from '~/pages/faq.vue'
import AccessibilityPage from '~/pages/accessibility.vue'

// Careers openings and the FAQ are admin-managed now (admin-v2 Plan 13) —
// mock the API transport with path-keyed fixtures.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'

const mockUseApiFetch = vi.mocked(useApiFetch)

const OPENINGS = [
  { id: '1', title: 'Projectionist', department: 'Operations', type: 'Full-time', description: 'Maintain and operate laser projection.' },
  { id: '2', title: 'Front of House Team Member', department: 'Guest Services', type: 'Part-time', description: 'Welcome guests.' },
  { id: '3', title: 'Kitchen & Bar Staff', department: 'Food & Beverage', type: 'Part-time', description: 'Prepare and serve.' },
]

const FAQ = [
  {
    category: 'Tickets & Booking',
    items: [{ question: 'How do I purchase tickets?', answer: 'Online through our website.' }],
  },
  {
    category: 'Policies',
    items: [{ question: 'What is your bag policy?', answer: 'Small bags only.' }],
  },
]

function fetchTuple<T>(payload: T) {
  return {
    data: ref({ data: payload }),
    pending: ref(false),
    error: ref(null),
    refresh: vi.fn(),
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  mockUseApiFetch.mockImplementation(((path: string) => {
    if (path === '/api/job-openings') return fetchTuple(OPENINGS)
    if (path === '/api/faq') return fetchTuple(FAQ)
    // Site contacts (admin-v3 Plan 02): null → pages render fallback values.
    if (path === '/api/site-content/contacts') return fetchTuple({ contacts: null })
    // Careers benefits (admin-v6 G5): null → page renders its built-in list.
    if (path === '/api/site-content/careers') return fetchTuple({ benefits: null })
    // Accessibility prose (admin-v6 G4): null → page renders its built-in copy.
    if (path === '/api/site-content/accessibility') return fetchTuple({ accessibility: null })
    throw new Error(`Unexpected fetch: ${path}`)
  }) as any)
})

describe('Careers Page', () => {
  it('renders page title', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.find('.careers-page__title').text()).toBe('Careers')
  })

  it('renders intro text', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.find('.careers-page__intro').text()).toContain('people who care about the details')
  })

  it('renders job openings from the API', async () => {
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

  it('renders the built-in benefits list when none are admin-saved', async () => {
    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.text()).toContain('Free movie tickets')
    expect(wrapper.text()).toContain('Flexible scheduling')
  })

  it('renders admin-saved benefits over the built-in list (G5)', async () => {
    mockUseApiFetch.mockImplementation(((path: string) => {
      if (path === '/api/job-openings') return fetchTuple(OPENINGS)
      if (path === '/api/site-content/contacts') return fetchTuple({ contacts: null })
      if (path === '/api/site-content/careers') {
        return fetchTuple({ benefits: ['Curated by an admin', 'Second admin perk'] })
      }
      throw new Error(`Unexpected fetch: ${path}`)
    }) as any)

    const wrapper = await mountSuspended(CareersPage)
    expect(wrapper.text()).toContain('Curated by an admin')
    expect(wrapper.text()).toContain('Second admin perk')
    // The built-in defaults are replaced, not appended.
    expect(wrapper.text()).not.toContain('Free movie tickets')
  })

  it('renders application instructions with email link', async () => {
    const wrapper = await mountSuspended(CareersPage)
    const link = wrapper.find('a[href="mailto:careers@finalcut.test"]')
    expect(link.exists()).toBe(true)
  })
})

describe('FAQ Page', () => {
  it('renders categories and questions from the API', async () => {
    const wrapper = await mountSuspended(FaqPage)
    expect(wrapper.find('.faq-page__title').text()).toBe('Frequently Asked Questions')
    expect(wrapper.text()).toContain('Tickets & Booking')
    expect(wrapper.text()).toContain('How do I purchase tickets?')
    expect(wrapper.text()).toContain('Policies')
  })
})

describe('Accessibility Page', () => {
  it('renders page title', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    expect(wrapper.find('.accessibility-page__title').text()).toBe('Accessibility')
  })

  it('renders the built-in commitment statement when none is admin-saved', async () => {
    const wrapper = await mountSuspended(AccessibilityPage)
    expect(wrapper.find('.accessibility-page__intro').text()).toContain('inclusive experience')
  })

  it('renders admin-saved prose over the built-in copy (G4)', async () => {
    mockUseApiFetch.mockImplementation(((path: string) => {
      if (path === '/api/site-content/contacts') return fetchTuple({ contacts: null })
      if (path === '/api/site-content/accessibility') {
        return fetchTuple({
          accessibility: {
            intro: 'We welcome every guest, every way.',
            assistedListening: 'Loops in every room.',
            wheelchairSeating: 'Front-and-center accessible seats.',
            openCaption: 'Captions nightly.',
            audioDescription: 'Described tracks on request.',
            sensoryFriendly: 'Calm screenings weekly.',
            serviceAnimals: 'Service animals always welcome.',
          },
        })
      }
      throw new Error(`Unexpected fetch: ${path}`)
    }) as any)

    const wrapper = await mountSuspended(AccessibilityPage)
    expect(wrapper.find('.accessibility-page__intro').text()).toBe('We welcome every guest, every way.')
    expect(wrapper.text()).toContain('Loops in every room.')
    expect(wrapper.text()).toContain('Service animals always welcome.')
    expect(wrapper.text()).not.toContain('inclusive experience')
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
