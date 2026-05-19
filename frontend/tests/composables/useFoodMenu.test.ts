import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock the API client before importing the composable so the mocked symbol
// is picked up at module graph resolution time.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch, useApiFetch } from '~/utils/api'
import { useFoodMenu } from '~/composables/useFoodMenu'
import { menuData } from '~/data/menu'

const mockApiFetch = vi.mocked(apiFetch)
const mockUseApiFetch = vi.mocked(useApiFetch)

/**
 * The overlay in menuData keyed by id. Used to assert enrichment actually
 * pulled the expected editorial fields onto API-returned items.
 */
const POP_SM_OVERLAY = menuData.find((item) => item.id === 'pop-sm')

describe('useFoodMenu', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // Reset shared useState refs to keep cross-test isolation deterministic.
    const rawItems = useState<unknown>('food-menu-items')
    const rawError = useState<unknown>('food-menu-error')
    const rawLoading = useState<unknown>('food-menu-loading')
    rawItems.value = []
    rawError.value = null
    rawLoading.value = false
  })

  // ─── fetchAll() — cross-location shared endpoint ─────────────────────────

  describe('fetchAll()', () => {
    it('calls /api/food-menu with no location segment', () => {
      // useApiFetch is the SSR-compatible useFetch wrapper.
      mockUseApiFetch.mockReturnValue({
        data: ref({ data: [] }),
        error: ref(null),
        pending: ref(false),
        refresh: vi.fn(),
        execute: vi.fn(),
        status: ref('success'),
      } as any)

      const { fetchAll } = useFoodMenu()
      fetchAll()

      expect(mockUseApiFetch).toHaveBeenCalledWith('/api/food-menu', {
        key: 'food-menu-all',
      })
      // Must NOT contain a location segment.
      const calledPath = mockUseApiFetch.mock.calls[0]?.[0] as string
      expect(calledPath).toBe('/api/food-menu')
      expect(calledPath).not.toMatch(/\/locations\//)
    })

    it('returns items with available_at arrays from the response', () => {
      const downtown = {
        id: 'pop-sm',
        name: 'Small Popcorn',
        description: 'Classic.',
        price: 699,
        category: 'popcorn',
        imageUrl: '/img.jpg',
        allergens: [],
        dietary: [],
        available: true,
        available_at: ['downtown'],
      }
      const everywhere = {
        id: 'pop-lg',
        name: 'Large Popcorn',
        description: 'Big.',
        price: 999,
        category: 'popcorn',
        imageUrl: '/img2.jpg',
        allergens: [],
        dietary: [],
        available: true,
        available_at: ['downtown', 'uptown'],
      }

      mockUseApiFetch.mockReturnValue({
        data: ref({ data: [downtown, everywhere] }),
        error: ref(null),
        pending: ref(false),
        refresh: vi.fn(),
        execute: vi.fn(),
        status: ref('success'),
      } as any)

      const { fetchAll } = useFoodMenu()
      const { data } = fetchAll()

      const items = (data as any).value?.data ?? []
      expect(items).toHaveLength(2)

      const small = items.find((i: any) => i.id === 'pop-sm')
      expect(small?.available_at).toEqual(['downtown'])

      const large = items.find((i: any) => i.id === 'pop-lg')
      expect(large?.available_at).toEqual(['downtown', 'uptown'])
    })

    it('returns available_at as an empty array for items with no availability', () => {
      const noAvailability = {
        id: 'pop-sm',
        name: 'Small Popcorn',
        description: 'Classic.',
        price: 699,
        category: 'popcorn',
        imageUrl: '/img.jpg',
        allergens: [],
        dietary: [],
        available: false,
        available_at: [],
      }

      mockUseApiFetch.mockReturnValue({
        data: ref({ data: [noAvailability] }),
        error: ref(null),
        pending: ref(false),
        refresh: vi.fn(),
        execute: vi.fn(),
        status: ref('success'),
      } as any)

      const { fetchAll } = useFoodMenu()
      const { data } = fetchAll()

      const items = (data as any).value?.data ?? []
      expect(items[0]?.available_at).toEqual([])
    })
  })

  // ─── fetchMenu() — per-location path (booking flow) ───────────────────────

  describe('fetchMenu()', () => {
    it('hits /api/food-menu when no location slug is provided', async () => {
      // No-slug path now fetches the cross-location menu so the booking
      // checkout flow gets each item's `available_at` for the dimming guard.
      mockApiFetch.mockResolvedValue({
        data: [
          {
            id: 'pop-sm',
            name: 'Small Popcorn',
            description: 'Classic buttered popcorn in a small tub.',
            price: 699,
            category: 'popcorn',
            imageUrl: 'https://cdn.example.test/finalcut/concessions/popcorn_sm.webp',
            allergens: ['dairy'],
            dietary: [],
            available: true,
            available_at: ['downtown', 'uptown'],
          },
        ],
      })

      const { items, fetchMenu } = useFoodMenu()

      await fetchMenu()

      expect(mockApiFetch).toHaveBeenCalledWith('/api/food-menu')
      expect(items.value.length).toBe(1)
      // Cross-location path still runs enrichment → editorial fields come through.
      const popSm = items.value.find((i) => i.id === 'pop-sm')
      expect(popSm?.size).toBe(POP_SM_OVERLAY?.size)
      expect(popSm?.curator).toBe(POP_SM_OVERLAY?.curator)
      expect(popSm?.available_at).toEqual(['downtown', 'uptown'])
    })

    it('fetches the location-scoped menu when a slug is provided and enriches items', async () => {
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
              imageUrl: 'https://cdn.example.test/finalcut/concessions/popcorn_sm.webp',
              allergens: ['dairy'],
              dietary: [],
              available: true,
            },
          ],
        },
      })

      const { items, fetchMenu } = useFoodMenu()
      await fetchMenu('downtown')

      expect(mockApiFetch).toHaveBeenCalledWith('/api/locations/downtown/food-menu')
      expect(items.value).toHaveLength(1)
      const popSm = items.value[0]
      expect(popSm?.id).toBe('pop-sm')
      // Editorial overlay fields were merged in client-side.
      expect(popSm?.size).toBe(POP_SM_OVERLAY?.size)
      expect(popSm?.curator).toBe(POP_SM_OVERLAY?.curator)
    })

    it('preserves item-level fields over overlay defaults when the API provides them', async () => {
      mockApiFetch.mockResolvedValue({
        data: {
          popcorn: [
            {
              id: 'pop-sm',
              name: 'Small Popcorn',
              description: 'Classic buttered popcorn in a small tub.',
              price: 699,
              category: 'popcorn',
              imageUrl: 'https://cdn.example.test/finalcut/concessions/popcorn_sm.webp',
              allergens: ['dairy'],
              dietary: [],
              available: true,
              curator: 'Chef Marco', // API override wins over editorial overlay.
            },
          ],
        },
      })

      const { items, fetchMenu } = useFoodMenu()
      await fetchMenu('downtown')

      expect(items.value[0]?.curator).toBe('Chef Marco')
    })

    it('falls back to static menuData on API error and records the error message', async () => {
      mockApiFetch.mockRejectedValue(new Error('Service unavailable'))

      const { items, error, fetchMenu } = useFoodMenu()
      await fetchMenu('downtown')

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

    it('passes the correct slug when fetchMenu is called with different slugs', async () => {
      mockApiFetch.mockResolvedValue({ data: { popcorn: [] } })

      const { fetchMenu } = useFoodMenu()
      await fetchMenu('downtown')
      expect(mockApiFetch).toHaveBeenLastCalledWith('/api/locations/downtown/food-menu')

      await fetchMenu('uptown')
      expect(mockApiFetch).toHaveBeenLastCalledWith('/api/locations/uptown/food-menu')
    })

    it('does not read or depend on useLocations().activeLocation', async () => {
      // This test guards the Task 2 contract: useFoodMenu must not read activeLocation.
      // Verify that calling fetchMenu does NOT read activeLocation — the
      // composable's location coupling was demoted in Plan 13 Task 2. The
      // no-slug path now fetches /api/food-menu (cross-location) regardless
      // of any activeLocation value, but it must not READ that ref.
      mockApiFetch.mockResolvedValue({ data: [] })
      const { activeLocation } = useLocations()
      activeLocation.value = { id: '1', name: 'Downtown', slug: 'downtown', address: '123 Main St' }

      const { fetchMenu } = useFoodMenu()
      await fetchMenu() // no slug arg → cross-location endpoint, never per-location

      expect(mockApiFetch).toHaveBeenCalledWith('/api/food-menu')
      expect(mockApiFetch).not.toHaveBeenCalledWith('/api/locations/downtown/food-menu')
    })
  })
})
