import { describe, it, expect, beforeEach, vi } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ContactPage from '~/pages/contact.vue'
import PrivateScreeningsPage from '~/pages/private-screenings.vue'

// Both pages consume admin-managed data (admin-v2 Plan 14) — mock the API
// transport with path-keyed fixtures.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'

const mockUseApiFetch = vi.mocked(useApiFetch)

const LOCATIONS = [
  {
    id: 'l1',
    slug: 'downtown',
    name: 'Downtown',
    street: '123 Cinema Boulevard',
    city: 'New York',
    state: 'NY',
    postal_code: '10001',
    country: 'US',
    phone: '(212) 555-0199',
    email: 'downtown@finalcut.test',
    latitude: 40.7128,
    longitude: -73.9352,
    timezone: 'America/New_York',
    hours: {
      monday: { open: '11:00', close: '23:00' },
      sunday: null,
    },
    address: '123 Cinema Boulevard, New York, NY 10001',
  },
]

const PACKAGES = [
  {
    id: 'p1',
    name: 'Birthday Party',
    description: 'Celebrate with a private screening.',
    startingPrice: 35000,
    features: ['Private auditorium for 2 hours', 'Dedicated party host'],
  },
  {
    id: 'p2',
    name: 'Corporate Event',
    description: 'Team building and launches.',
    startingPrice: 75000,
    features: ['Full auditorium rental'],
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
    if (path === '/api/locations') return fetchTuple(LOCATIONS)
    if (path === '/api/screening-packages') return fetchTuple(PACKAGES)
    throw new Error(`Unexpected fetch: ${path}`)
  }) as any)
})

describe('Contact Page', () => {
  it('renders venue hours from the API, with closed days marked', async () => {
    const wrapper = await mountSuspended(ContactPage)
    expect(wrapper.text()).toContain('11:00 AM – 11:00 PM')
    expect(wrapper.text()).toContain('Closed')
  })

  it('renders the venue phone and email', async () => {
    const wrapper = await mountSuspended(ContactPage)
    expect(wrapper.text()).toContain('(212) 555-0199')
    expect(wrapper.text()).toContain('downtown@finalcut.test')
  })

  it('keeps the editorial directions copy', async () => {
    const wrapper = await mountSuspended(ContactPage)
    expect(wrapper.text()).toContain('Parking garage located directly beneath the theater')
  })
})

describe('Private Screenings Page', () => {
  it('renders packages from the API with pricing', async () => {
    const wrapper = await mountSuspended(PrivateScreeningsPage)
    expect(wrapper.text()).toContain('Birthday Party')
    expect(wrapper.text()).toContain('Corporate Event')
    expect(wrapper.text()).toContain('$350')
  })

  it('renders package features', async () => {
    const wrapper = await mountSuspended(PrivateScreeningsPage)
    expect(wrapper.text()).toContain('Dedicated party host')
  })
})
