import { describe, it, expect, beforeEach, vi } from 'vitest'
import { isRef, unref } from 'vue'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { useMovies } from '~/composables/useMovies'

const mockUseApiFetch = vi.mocked(useApiFetch)

// useMovies now passes the location filter and cache key as reactive refs/
// computeds so the underlying useFetch refetches when the URL changes.
// Tests unwrap with `unref` so assertions read the resolved string values.
function unwrapCallOptions(callIndex = 0) {
  const opts = mockUseApiFetch.mock.calls[callIndex]?.[1] as Record<string, any> | undefined
  if (!opts) return undefined
  const query = opts.query
    ? Object.fromEntries(
        Object.entries(opts.query).map(([k, v]) => [k, isRef(v) ? unref(v) : v]),
      )
    : opts.query
  return { ...opts, query, key: isRef(opts.key) ? unref(opts.key) : opts.key }
}

describe('useMovies', () => {
  beforeEach(() => { vi.clearAllMocks() })

  // ── Base calls ──────────────────────────────────────────────────────────

  it('nowShowing fetches with status=now_showing', () => {
    const { nowShowing } = useMovies()
    nowShowing()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'now_showing' }),
    }))
  })

  it('nowShowing passes additional options', () => {
    const { nowShowing } = useMovies()
    nowShowing({ genre: 28, per_page: 10 })
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'now_showing', genre: 28, per_page: 10 }),
    }))
  })

  it('comingSoon fetches with status=coming_soon', () => {
    const { comingSoon } = useMovies()
    comingSoon()
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies', expect.objectContaining({
      query: expect.objectContaining({ status: 'coming_soon' }),
    }))
  })

  it('getMovie fetches by slug', () => {
    const { getMovie } = useMovies()
    getMovie('the-dark-knight')
    expect(mockUseApiFetch).toHaveBeenCalledWith('/api/movies/the-dark-knight')
  })

  // ── Location filter ─────────────────────────────────────────────────────

  it('nowShowing includes ?location= when location option is provided', () => {
    const { nowShowing } = useMovies()
    nowShowing({ location: 'downtown' })
    const opts = unwrapCallOptions()
    expect(opts?.query).toMatchObject({ status: 'now_showing', location: 'downtown' })
  })

  it('nowShowing leaves ?location= undefined when location option is omitted', () => {
    const { nowShowing } = useMovies()
    nowShowing()
    const opts = unwrapCallOptions()
    expect(opts?.query?.location).toBeUndefined()
  })

  it('comingSoon includes ?location= when location option is provided', () => {
    const { comingSoon } = useMovies()
    comingSoon({ location: 'uptown' })
    const opts = unwrapCallOptions()
    expect(opts?.query).toMatchObject({ status: 'coming_soon', location: 'uptown' })
  })

  it('nowShowing uses a location-scoped cache key when location is provided', () => {
    const { nowShowing } = useMovies()
    nowShowing({ location: 'downtown' })
    const opts = unwrapCallOptions()
    expect(opts?.key).toBe('movies-now-showing-downtown')
  })

  it('nowShowing uses the default cache key when no location is provided', () => {
    const { nowShowing } = useMovies()
    nowShowing()
    const opts = unwrapCallOptions()
    expect(opts?.key).toBe('movies-now-showing')
  })

  it('comingSoon uses a location-scoped cache key when location is provided', () => {
    const { comingSoon } = useMovies()
    comingSoon({ location: 'uptown' })
    const opts = unwrapCallOptions()
    expect(opts?.key).toBe('movies-coming-soon-uptown')
  })

  it('two different location values produce two different cache keys', () => {
    const { nowShowing } = useMovies()
    nowShowing({ location: 'downtown' })
    nowShowing({ location: 'uptown' })
    expect(unwrapCallOptions(0)?.key).toBe('movies-now-showing-downtown')
    expect(unwrapCallOptions(1)?.key).toBe('movies-now-showing-uptown')
  })

  it('passing a Ref<string> for location refetches when the ref changes', async () => {
    const { ref } = await import('vue')
    const slug = ref<string | undefined>('downtown')

    const { nowShowing } = useMovies()
    nowShowing({ location: slug })

    const initial = unwrapCallOptions()
    expect(initial?.key).toBe('movies-now-showing-downtown')
    expect(initial?.query?.location).toBe('downtown')

    slug.value = 'uptown'
    // Re-read the same call's resolved values — useFetch sees a reactive
    // ref under both the query and key fields, so on next fetch they
    // resolve to the new slug without a re-call to `nowShowing`.
    const after = unwrapCallOptions()
    expect(after?.key).toBe('movies-now-showing-uptown')
    expect(after?.query?.location).toBe('uptown')
  })
})
