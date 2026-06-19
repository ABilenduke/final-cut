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

function tuple<T>(data: T) {
  return { data: ref({ data }), pending: ref(false), error: ref(null), refresh: vi.fn() }
}

function mockContacts(contacts: SiteContacts | null, footerNav: Array<{ label: string, href: string }> | null = null) {
  mockUseApiFetch.mockImplementation(((path: string) => {
    if (path === '/api/site-content/contacts') return tuple({ contacts })
    if (path === '/api/site-content/navigation') return tuple({ header: null, footer: footerNav })
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

  it('renders the built-in footer nav when none is admin-saved (G1)', async () => {
    mockContacts(null, null)
    const wrapper = await mountSuspended(SiteFooter)

    const links = wrapper.findAll('.site-footer__nav-link').map(l => l.text())
    expect(links).toContain('Our Cinemas')
    expect(links).toContain('Private Screenings')
  })

  it('renders admin-saved footer nav over the built-in list (G1)', async () => {
    mockContacts(null, [
      { label: 'Visit Us', href: '/locations' },
      { label: 'Help', href: '/faq' },
    ])
    const wrapper = await mountSuspended(SiteFooter)

    const links = wrapper.findAll('.site-footer__nav-link').map(l => l.text())
    expect(links).toEqual(['Visit Us', 'Help'])
    expect(links).not.toContain('Private Screenings')
  })

  it('shows the required TMDB attribution: approved logo + verbatim notice', async () => {
    mockContacts(null)
    const wrapper = await mountSuspended(SiteFooter)

    // Notice text is mandated verbatim by the TMDB API terms — do not reword.
    expect(wrapper.find('.site-footer__tmdb-notice').text()).toBe(
      'This product uses the TMDB API but is not endorsed or certified by TMDB.',
    )

    // Approved logo links to TMDB and is the unmodified asset in public/.
    const link = wrapper.find('.site-footer__tmdb-link')
    expect(link.attributes('href')).toBe('https://www.themoviedb.org')

    const logo = wrapper.find('.site-footer__tmdb-logo')
    expect(logo.exists()).toBe(true)
    expect(logo.attributes('src')).toBe('/tmdb.svg')
    expect(logo.attributes('alt')).toBe('The Movie Database (TMDB)')
  })
})
