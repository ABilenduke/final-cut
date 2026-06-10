import { describe, it, expect, beforeEach, vi } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import SiteFooter from '~/components/layout/SiteFooter.vue'
import { fallbackSiteContacts } from '~/data/siteContacts'
import type { SiteContacts } from '~/data/siteContacts'

// Footer contact line is admin-editable (admin-v3 Plan 02) — mock the API
// transport with path-keyed fixtures.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'

const mockUseApiFetch = vi.mocked(useApiFetch)

function mockContacts(contacts: SiteContacts | null) {
  mockUseApiFetch.mockImplementation(((path: string) => {
    if (path === '/api/site-content/contacts') {
      return {
        data: ref({ data: { contacts } }),
        pending: ref(false),
        error: ref(null),
        refresh: vi.fn(),
      }
    }
    throw new Error(`Unexpected fetch: ${path}`)
  }) as any)
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('SiteFooter', () => {
  it('renders the admin-saved footer line when the API has one', async () => {
    mockContacts({
      ...fallbackSiteContacts,
      footerVenueName: 'Final Cut Uptown',
      footerAddress: '900 Marquee Way',
      footerPhone: '(212) 555-7000',
    })
    const wrapper = await mountSuspended(SiteFooter)

    const address = wrapper.find('.site-footer__address').text()
    expect(address).toContain('Final Cut Uptown')
    expect(address).toContain('900 Marquee Way')
    expect(address).toContain('(212) 555-7000')
  })

  it('falls back to the built-in line when the API returns null', async () => {
    mockContacts(null)
    const wrapper = await mountSuspended(SiteFooter)

    const address = wrapper.find('.site-footer__address').text()
    expect(address).toContain('Final Cut Theatre')
    expect(address).toContain('123 Cinema Boulevard')
  })
})
