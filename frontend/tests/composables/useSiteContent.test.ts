import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import type { MembershipContent } from '~/data/homepage'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useHomeContent, resolveMembershipContent, useSiteContacts, resolveSiteContacts, useCareersContent, resolveCareersBenefits } from '~/composables/useSiteContent'
import { fallbackSiteContacts } from '~/data/siteContacts'

const mockUseApiFetch = vi.mocked(useApiFetch)

const FALLBACK: MembershipContent = {
  eyebrow: 'Membership',
  title: 'Join the',
  titleEmphasis: 'Reel Society.',
  copy: 'Fallback copy.',
  priceLabel: 'Become a Member · $24/mo',
  ctaLabel: 'View all tiers',
  cardTier: 'Charter Member',
  cardNumber: 'No. 0047',
  cardValidThrough: 'Valid through 12 · 2027',
  cardSociety: 'Reel Society',
  cardTitle: 'Final',
  cardTitleEmphasis: 'Cut.',
  perks: [{ title: 'Unlimited screenings', detail: 'Every film, every night.' }],
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useHomeContent', () => {
  it('fetches /api/site-content/home with an SSR-dedup key', () => {
    mockUseApiFetch.mockReturnValue({
      data: ref(null),
      pending: ref(false),
      error: ref(null),
      refresh: vi.fn(),
    } as any)

    useHomeContent()

    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/site-content/home',
      expect.objectContaining({ key: 'site-content-home' }),
    )
  })
})

describe('resolveMembershipContent', () => {
  it('returns the fallback when the API blob is null or undefined', () => {
    expect(resolveMembershipContent(null, FALLBACK)).toBe(FALLBACK)
    expect(resolveMembershipContent(undefined, FALLBACK)).toBe(FALLBACK)
  })

  it('returns the admin-saved blob when present', () => {
    const saved: MembershipContent = { ...FALLBACK, copy: 'Saved in the admin.' }
    expect(resolveMembershipContent(saved, FALLBACK)).toBe(saved)
  })
})

describe('useSiteContacts', () => {
  it('fetches /api/site-content/contacts with an SSR-dedup key', () => {
    mockUseApiFetch.mockReturnValue({
      data: ref(null),
      pending: ref(false),
      error: ref(null),
      refresh: vi.fn(),
    } as any)

    useSiteContacts()

    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/site-content/contacts',
      expect.objectContaining({ key: 'site-content-contacts' }),
    )
  })
})

describe('resolveSiteContacts', () => {
  it('returns the fallback when the API blob is null', () => {
    expect(resolveSiteContacts(null, fallbackSiteContacts)).toBe(fallbackSiteContacts)
    expect(resolveSiteContacts(undefined, fallbackSiteContacts)).toBe(fallbackSiteContacts)
  })

  it('returns the admin-saved blob when present', () => {
    const saved = { ...fallbackSiteContacts, conciergeEmail: 'vip@finalcut.test' }
    expect(resolveSiteContacts(saved, fallbackSiteContacts)).toBe(saved)
  })
})

describe('useCareersContent', () => {
  beforeEach(() => vi.clearAllMocks())

  it('fetches the careers endpoint with a dedupe key', () => {
    mockUseApiFetch.mockReturnValue({
      data: ref(null),
      pending: ref(false),
      error: ref(null),
      refresh: vi.fn(),
    } as any)

    useCareersContent()

    expect(mockUseApiFetch).toHaveBeenCalledWith(
      '/api/site-content/careers',
      expect.objectContaining({ key: 'site-content-careers' }),
    )
  })
})

describe('resolveCareersBenefits', () => {
  const fallback = ['Free tickets', 'Discounts']

  it('returns the fallback when nothing is saved', () => {
    expect(resolveCareersBenefits(null, fallback)).toBe(fallback)
    expect(resolveCareersBenefits(undefined, fallback)).toBe(fallback)
  })

  it('returns the fallback when the saved list is empty (never renders blank)', () => {
    expect(resolveCareersBenefits([], fallback)).toBe(fallback)
  })

  it('returns the admin-saved benefits when present', () => {
    const saved = ['Curated perk', 'Another perk']
    expect(resolveCareersBenefits(saved, fallback)).toBe(saved)
  })
})
