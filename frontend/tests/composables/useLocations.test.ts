import { describe, it, expect, beforeEach, vi } from 'vitest'
import { nextTick } from 'vue'

// Mock the API client module
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
const mockApiFetch = vi.mocked(apiFetch)

import { useLocations } from '~/composables/useLocations'

// Mock localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {}
  return {
    getItem: vi.fn((key: string) => store[key] ?? null),
    setItem: vi.fn((key: string, value: string) => { store[key] = value }),
    removeItem: vi.fn((key: string) => { delete store[key] }),
    clear: vi.fn(() => { store = {} }),
  }
})()

Object.defineProperty(globalThis, 'localStorage', { value: localStorageMock })

const DOWNTOWN = { id: '1', name: 'Downtown', slug: 'downtown', address: '123 Main St' }
const UPTOWN = { id: '2', name: 'Uptown', slug: 'uptown', address: '456 Elm St' }

describe('useLocations', () => {
  beforeEach(() => {
    localStorageMock.clear()
    vi.clearAllMocks()
    // Reset shared useState refs to prevent cross-test leakage
    const { locations, activeLocation } = useLocations()
    locations.value = []
    activeLocation.value = null
  })

  it('initializes with empty locations and null activeLocation', () => {
    const { locations, activeLocation } = useLocations()
    expect(locations.value).toEqual([])
    expect(activeLocation.value).toBeNull()
  })

  it('setLocation updates activeLocation and persists to localStorage', () => {
    const { locations, activeLocation, setLocation } = useLocations()
    locations.value = [DOWNTOWN, UPTOWN]

    setLocation('uptown')

    expect(activeLocation.value?.slug).toBe('uptown')
    expect(localStorageMock.setItem).toHaveBeenCalledWith('active-location', 'uptown')
  })

  it('setLocation does nothing for unknown slug', () => {
    const { locations, activeLocation, setLocation } = useLocations()
    locations.value = [DOWNTOWN]
    activeLocation.value = null

    setLocation('nonexistent')

    expect(activeLocation.value).toBeNull()
    expect(localStorageMock.setItem).not.toHaveBeenCalled()
  })

  it('rehydrates from localStorage when locations are populated', async () => {
    localStorageMock.setItem('active-location', 'uptown')
    vi.clearAllMocks() // Clear the setItem mock call above

    const { locations, activeLocation } = useLocations()
    activeLocation.value = null

    locations.value = [DOWNTOWN, UPTOWN]

    await nextTick()

    expect(activeLocation.value?.slug).toBe('uptown')
  })

  it('falls back to first location when stored slug is not found', async () => {
    localStorageMock.setItem('active-location', 'gone')
    vi.clearAllMocks()

    const { locations, activeLocation } = useLocations()
    activeLocation.value = null

    locations.value = [DOWNTOWN]

    await nextTick()

    expect(activeLocation.value?.slug).toBe('downtown')
  })

  it('falls back to first location when no stored slug exists', async () => {
    const { locations, activeLocation } = useLocations()
    activeLocation.value = null

    locations.value = [DOWNTOWN]

    await nextTick()

    expect(activeLocation.value?.slug).toBe('downtown')
  })

  it('fetchLocations populates locations from API', async () => {
    mockApiFetch.mockResolvedValue({ data: [DOWNTOWN, UPTOWN] })
    const { locations, fetchLocations } = useLocations()
    await fetchLocations()
    expect(locations.value).toEqual([DOWNTOWN, UPTOWN])
    expect(mockApiFetch).toHaveBeenCalledWith('/api/locations')
  })

  it('fetchLocations triggers rehydration from localStorage', async () => {
    localStorageMock.setItem('active-location', 'uptown')
    vi.clearAllMocks()
    mockApiFetch.mockResolvedValue({ data: [DOWNTOWN, UPTOWN] })
    const { activeLocation, fetchLocations } = useLocations()
    activeLocation.value = null
    await fetchLocations()
    await nextTick()
    expect(activeLocation.value?.slug).toBe('uptown')
  })

  it('fetchLocations with empty response keeps activeLocation null', async () => {
    mockApiFetch.mockResolvedValue({ data: [] })
    const { activeLocation, fetchLocations } = useLocations()
    await fetchLocations()
    await nextTick()
    expect(activeLocation.value).toBeNull()
  })
})
