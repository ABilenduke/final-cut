import type { MenuItem } from '~/types/menu-item'
import { menuData, editorialOverlayFor } from '~/data/menu'
import { apiFetch, useApiFetch } from '~/utils/api'

/**
 * Fetches the food menu, either cross-location (Task 7 / public browse) or
 * per-location (booking checkout flow).
 *
 * Three fetch strategies:
 * - `fetchAll()` — calls `/api/food-menu` (no location segment). Returns every
 *   item across all venues; each item carries `available_at: string[]` so the
 *   browse page can render availability captions. Use for SSR content pages.
 * - `fetchMenu(slug)` — calls `/api/locations/{slug}/food-menu` per-location.
 *   Used by the purchase checkout flow. Enriches with editorial overlay. Falls
 *   back to static menuData on error.
 * - `fetchMenu()` (no slug) — falls back immediately to static editorial menu.
 *   Used in dev environments without a backend.
 *
 * Task 2 note: the activeLocation watcher has been removed. This composable
 * no longer reads useLocations().activeLocation at all. The location slug is
 * passed explicitly by the consuming page or booking flow.
 */
export function useFoodMenu() {
  const items = useState<MenuItem[]>('food-menu-items', () => [])
  const loading = useState<boolean>('food-menu-loading', () => false)
  const error = useState<string | null>('food-menu-error', () => null)

  function enrich(item: MenuItem): MenuItem {
    const overlay = editorialOverlayFor(item.id)
    return {
      ...item,
      size: item.size ?? overlay.size,
      curator: item.curator ?? overlay.curator,
      flag: item.flag ?? overlay.flag,
      gradient: item.gradient ?? overlay.gradient,
      glyph: item.glyph ?? overlay.glyph,
    }
  }

  /**
   * SSR-friendly fetch of the cross-location menu endpoint.
   *
   * Returns useFetch's reactive refs directly so the calling page can await
   * the data via useAsyncData/useFetch SSR semantics. Each item in the response
   * includes `available_at: string[]` (location slugs). Items with an empty
   * `available_at` array are excluded from the returned data.
   *
   * Cache key is stable so Nuxt deduplicates concurrent SSR renders.
   */
  function fetchAll() {
    return useApiFetch<{ data: MenuItem[] }>('/api/food-menu', {
      key: 'food-menu-all',
    })
  }

  /**
   * Fetch the food menu. Pass a locationSlug to hit the per-location API;
   * omit it to fall back immediately to the static editorial menu (used by
   * the /food-drink browse page until Task 7 lands the shared endpoint).
   *
   * NOTE: This path is preserved for the booking checkout flow. Do not remove.
   */
  async function fetchMenu(locationSlug?: string): Promise<void> {
    loading.value = true
    error.value = null

    if (!locationSlug) {
      // No location provided — fall back to the static editorial menu.
      items.value = menuData.map(enrich)
      loading.value = false
      return
    }

    try {
      const response = await apiFetch<{ data: Record<string, MenuItem[]> }>(
        `/api/locations/${locationSlug}/food-menu`,
      )
      const flat = Object.values(response.data).flat()
      items.value = flat.map(enrich)
    } catch (err) {
      // Graceful fallback — keep the catalog visible with the static menu.
      error.value = err instanceof Error ? err.message : 'Failed to load menu.'
      items.value = menuData.map(enrich)
    } finally {
      loading.value = false
    }
  }

  /** Items grouped by category, in catalog order. */
  const byCategory = computed<Record<string, MenuItem[]>>(() => {
    const grouped: Record<string, MenuItem[]> = {}
    for (const item of items.value) {
      const bucket = grouped[item.category] ?? []
      bucket.push(item)
      grouped[item.category] = bucket
    }
    return grouped
  })

  return {
    items: readonly(items),
    loading: readonly(loading),
    error: readonly(error),
    byCategory,
    fetchAll,
    fetchMenu,
  }
}
