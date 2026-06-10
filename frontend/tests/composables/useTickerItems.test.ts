import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useTickerItems, resolveTickerItems } from '~/composables/useTickerItems'
import type { TickerItem } from '~/types/ticker-item'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('useTickerItems', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches from /api/ticker-items with an explicit SSR-dedup key', () => {
    useTickerItems()
    const [path, opts] = mockUseApiFetch.mock.calls[0] as [string, { key?: string }]
    expect(path).toBe('/api/ticker-items')
    expect(opts.key).toBe('ticker-items')
  })

  it('returns the result of useApiFetch', () => {
    const fakeResult = {
      data: { value: { data: [] } },
      pending: { value: false },
      error: { value: null },
      refresh: vi.fn(),
    }
    mockUseApiFetch.mockReturnValueOnce(fakeResult as any)

    expect(useTickerItems()).toBe(fakeResult)
  })
})

describe('resolveTickerItems', () => {
  const fallback = [{ label: 'Brand', text: 'Final Cut' }]

  it('prefers API items when present', () => {
    const api: TickerItem[] = [
      { id: '1', label: 'Now Showing', text: 'Dune · 19:00', href: null },
    ]
    expect(resolveTickerItems(api, fallback)).toEqual([
      { label: 'Now Showing', text: 'Dune · 19:00' },
    ])
  })

  it('carries href through when set', () => {
    const api: TickerItem[] = [
      { id: '1', label: 'Event', text: 'Retrospective', href: '/events/kubrick' },
    ]
    expect(resolveTickerItems(api, fallback)[0]).toMatchObject({ href: '/events/kubrick' })
  })

  it('falls back to the hardcoded items when the API list is empty or absent', () => {
    expect(resolveTickerItems([], fallback)).toBe(fallback)
    expect(resolveTickerItems(undefined, fallback)).toBe(fallback)
  })
})
