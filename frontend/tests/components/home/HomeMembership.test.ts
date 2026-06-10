import { describe, it, expect, beforeEach, vi } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeMembership from '~/components/home/HomeMembership.vue'
import { membershipContent } from '~/data/homepage'
import type { MembershipContent } from '~/data/homepage'

// The membership pitch is admin-editable (admin-v2 Plan 15) — mock the API
// transport with path-keyed fixtures, same idiom as the other home sections.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'

const mockUseApiFetch = vi.mocked(useApiFetch)

const SAVED: MembershipContent = {
  ...membershipContent,
  eyebrow: 'From The Admin',
  copy: 'Admin-authored pitch copy.',
  priceLabel: 'Join · $30/mo',
  perks: [{ title: 'Secret screenings', detail: 'Unlisted, members only.' }],
}

function mockHomeContent(membership: MembershipContent | null) {
  mockUseApiFetch.mockImplementation(((path: string) => {
    if (path === '/api/site-content/home') {
      return {
        data: ref({ data: { membership } }),
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

describe('HomeMembership', () => {
  it('renders the admin-saved blob when the API has one', async () => {
    mockHomeContent(SAVED)
    const wrapper = await mountSuspended(HomeMembership)

    expect(wrapper.text()).toContain('From The Admin')
    expect(wrapper.text()).toContain('Admin-authored pitch copy.')
    expect(wrapper.text()).toContain('Join · $30/mo')
    expect(wrapper.text()).toContain('Secret screenings')
    expect(wrapper.text()).not.toContain(membershipContent.copy)
  })

  it('falls back to the built-in copy when the API returns null', async () => {
    mockHomeContent(null)
    const wrapper = await mountSuspended(HomeMembership)

    expect(wrapper.text()).toContain(membershipContent.eyebrow)
    expect(wrapper.text()).toContain(membershipContent.priceLabel)
    expect(wrapper.text()).toContain(membershipContent.perks[0]!.title)
  })
})
