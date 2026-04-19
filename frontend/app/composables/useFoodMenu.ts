import type { MenuItem } from '~/types/menu-item'
import { menuData, editorialOverlayFor } from '~/data/menu'
import { apiFetch } from '~/utils/api'

/**
 * Fetches the food menu for the active location and enriches each item with the
 * editorial overlay (curator/size/flag/gradient/glyph). Falls back to static
 * `menuData` when the API is unavailable so the catalog still renders in dev
 * environments without a backend.
 */
export function useFoodMenu() {
  const { activeLocation } = useLocations()
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

  async function fetchMenu(): Promise<void> {
    loading.value = true
    error.value = null

    if (!activeLocation.value) {
      // No location yet — fall back to the static editorial menu so the page renders.
      items.value = menuData.map(enrich)
      loading.value = false
      return
    }

    try {
      const response = await apiFetch<{ data: Record<string, MenuItem[]> }>(
        `/api/locations/${activeLocation.value.slug}/food-menu`,
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

  // Refetch when the user switches locations mid-session so the catalog
  // always reflects the active location's inventory. Client-only — the
  // watcher would otherwise fire during SSR hydration, which we don't
  // want (fetchMenu() is called directly by the consuming page).
  if (import.meta.client) {
    watch(
      () => activeLocation.value?.slug,
      (slug, prev) => {
        if (slug && slug !== prev) fetchMenu()
      },
    )
  }

  return {
    items: readonly(items),
    loading: readonly(loading),
    error: readonly(error),
    byCategory,
    fetchMenu,
  }
}
