import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useFaq } from '~/composables/useFaq'
import { useJobOpenings } from '~/composables/useJobOpenings'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('useFaq / useJobOpenings', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('useFaq fetches /api/faq with an SSR-dedup key', () => {
    useFaq()
    const [path, opts] = mockUseApiFetch.mock.calls[0] as [string, { key?: string }]
    expect(path).toBe('/api/faq')
    expect(opts.key).toBe('faq')
  })

  it('useJobOpenings fetches /api/job-openings with an SSR-dedup key', () => {
    useJobOpenings()
    const [path, opts] = mockUseApiFetch.mock.calls[0] as [string, { key?: string }]
    expect(path).toBe('/api/job-openings')
    expect(opts.key).toBe('job-openings')
  })
})
