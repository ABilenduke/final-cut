import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock the API client before importing the composable so the mocked symbol
// is picked up at module graph resolution time.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
import { useFoodMenu } from '~/composables/useFoodMenu'
import { useLocations } from '~/composables/useLocations'
import { menuData } from '~/data/menu'

const mockApiFetch = vi.mocked(apiFetch)

const DOWNTOWN = { id: '1', name: 'Downtown', slug: 'downtown', address: '123 Main St' }
const UPTOWN = { id: '2', name: 'Uptown', slug: 'uptown', address: '456 Elm St' }

/**
 * The overlay in menuData keyed by id. Used to assert enrichment actually
 * pulled the expected editorial fields onto API-returned items.
 */
const POP_SM_OVERLAY = menuData.find((item) => item.id === 'pop-sm')

describe('useFoodMenu', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    const { locations, activeLocation } = useLocations()
    locations.value = []
    activeLocation.value = null
    const { items, error, loading } = useFoodMenu()
    // The composable exposes readonly refs, but the underlying useState
    // keys still mutate via the writable handles. Force a reset through
    // Nuxt's useState to keep cross-test isolation deterministic.
    const rawItems = useState<unknown>('food-menu-items')
    const rawError = useState<unknown>('food-menu-error')
    const rawLoading = useState<unknown>('food-menu-loading')
    rawItems.value = []
    rawError.value = null
    rawLoading.value = false
    void items; void error; void loading
  })

  it('falls back to static menuData when no active location is set', async () => {
    const { items, fetchMenu } = useFoodMenu()

    await fetchMenu()

    expect(mockApiFetch).not.toHaveBeenCalled()
    expect(items.value.length).toBe(menuData.length)
    // Fallback path still runs enrichment → editorial fields come through.
    const popSm = items.value.find((i) => i.id === 'pop-sm')
    expect(popSm?.size).toBe(POP_SM_OVERLAY?.size)
    expect(popSm?.curator).toBe(POP_SM_OVERLAY?.curator)
  })

  it('fetches the location-scoped menu and enriches items with editorial overlays', async () => {
    const { activeLocation } = useLocations()
    activeLocation.value = DOWNTOWN

    // Simulate the backend returning items WITHOUT editorial fields.
    mockApiFetch.mockResolvedValue({
      data: {
        popcorn: [
          {
            id: 'pop-sm',
            name: 'Small Popcorn',
            description: 'Classic buttered popcorn in a small tub.',
            price: 699,
            category: 'popcorn',
            imageUrl: '/images/menu/popcorn-small.jpg',
            allergens: ['dairy'],
            dietary: [],
            available: true,
          },
        ],
      },
    })

    const { items, fetchMenu } = useFoodMenu()
    await fetchMenu()

    expect(mockApiFetch).toHaveBeenCalledWith('/api/locations/downtown/food-menu')
    expect(items.value).toHaveLength(1)
    const popSm = items.value[0]
    expect(popSm?.id).toBe('pop-sm')
    // Editorial overlay fields were merged in client-side.
    expect(popSm?.size).toBe(POP_SM_OVERLAY?.size)
    expect(popSm?.curator).toBe(POP_SM_OVERLAY?.curator)
  })

  it('preserves item-level fields over overlay defaults when the API provides them', async () => {
    const { activeLocation } = useLocations()
    activeLocation.value = DOWNTOWN

    mockApiFetch.mockResolvedValue({
      data: {
        popcorn: [
          {
            id: 'pop-sm',
            name: 'Small Popcorn',
            description: 'Classic buttered popcorn in a small tub.',
            price: 699,
            category: 'popcorn',
            imageUrl: '/images/menu/popcorn-small.jpg',
            allergens: ['dairy'],
            dietary: [],
            available: true,
            curator: 'Chef Marco', // API override wins over editorial overlay.
          },
        ],
      },
    })

    const { items, fetchMenu } = useFoodMenu()
    await fetchMenu()

    expect(items.value[0]?.curator).toBe('Chef Marco')
  })

  it('falls back to static menuData on API error and records the error message', async () => {
    const { activeLocation } = useLocations()
    activeLocation.value = DOWNTOWN

    mockApiFetch.mockRejectedValue(new Error('Service unavailable'))

    const { items, error, fetchMenu } = useFoodMenu()
    await fetchMenu()

    expect(items.value.length).toBe(menuData.length)
    expect(error.value).toBe('Service unavailable')
  })

  it('groups items by category via the byCategory computed', async () => {
    const { items, byCategory, fetchMenu } = useFoodMenu()
    await fetchMenu()

    expect(items.value.length).toBeGreaterThan(0)
    // Every category bucket must contain only items of that category.
    for (const [category, bucket] of Object.entries(byCategory.value)) {
      for (const item of bucket) {
        expect(item.category).toBe(category)
      }
    }
  })

  it('requests the new location slug when fetchMenu runs after a switch', async () => {
    const { activeLocation } = useLocations()
    activeLocation.value = DOWNTOWN

    mockApiFetch.mockResolvedValue({ data: { popcorn: [] } })

    const { fetchMenu } = useFoodMenu()
    await fetchMenu()
    expect(mockApiFetch).toHaveBeenLastCalledWith('/api/locations/downtown/food-menu')

    // Switch location and explicitly refetch (mirrors the page-level
    // behaviour; the composable's internal watcher covers the same path
    // in the browser but needs a real component context to be observable).
    activeLocation.value = UPTOWN
    await fetchMenu()
    expect(mockApiFetch).toHaveBeenLastCalledWith('/api/locations/uptown/food-menu')
  })
})
