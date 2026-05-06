import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import { usePublicLocations } from '~/composables/usePublicLocations'

const mockUseApiFetch = vi.mocked(useApiFetch)

describe('usePublicLocations', () => {
  beforeEach(() => { vi.clearAllMocks() })

  describe('fetchLocations', () => {
    it('calls /api/locations', () => {
      const { fetchLocations } = usePublicLocations()
      fetchLocations()
      expect(mockUseApiFetch).toHaveBeenCalledWith('/api/locations', expect.anything())
    })

    it('returns the result of useApiFetch', () => {
      const mockReturn = { data: { value: { data: [] } }, error: { value: null } }
      mockUseApiFetch.mockReturnValue(mockReturn as any)

      const { fetchLocations } = usePublicLocations()
      const result = fetchLocations()

      expect(result).toBe(mockReturn)
    })
  })

  describe('fetchBySlug', () => {
    it('calls /api/locations/:slug', () => {
      const { fetchBySlug } = usePublicLocations()
      fetchBySlug('downtown')
      expect(mockUseApiFetch).toHaveBeenCalledWith('/api/locations/downtown')
    })

    it('calls the correct URL for a different slug', () => {
      const { fetchBySlug } = usePublicLocations()
      fetchBySlug('uptown')
      expect(mockUseApiFetch).toHaveBeenCalledWith('/api/locations/uptown')
    })

    it('returns the result of useApiFetch', () => {
      const mockReturn = { data: { value: { data: null } }, error: { value: null } }
      mockUseApiFetch.mockReturnValue(mockReturn as any)

      const { fetchBySlug } = usePublicLocations()
      const result = fetchBySlug('downtown')

      expect(result).toBe(mockReturn)
    })

    it('propagates error from useApiFetch on 404', () => {
      const notFoundError = { statusCode: 404, message: 'Not Found' }
      const mockReturn = { data: { value: null }, error: { value: notFoundError } }
      mockUseApiFetch.mockReturnValue(mockReturn as any)

      const { fetchBySlug } = usePublicLocations()
      const result = fetchBySlug('nonexistent-slug')

      expect(result.error.value).toEqual(notFoundError)
    })
  })
})
