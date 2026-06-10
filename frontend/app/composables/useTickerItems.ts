import type { TickerItem, TickerItemsResponse } from '~/types/ticker-item'
import { useApiFetch } from '~/utils/api'

/** The NeuralTicker component's item shape. */
export interface TickerDisplayItem {
  label: string
  text: string
  href?: string
}

/**
 * SSR-friendly fetch wrapper for the admin-curated Neural Ticker items.
 * Returns the useFetch tuple — data, pending, error, refresh.
 *
 * The explicit key 'ticker-items' prevents duplicate fetches when the
 * default layout renders on multiple SSR routes in one request graph.
 *
 * The endpoint returns only currently-active, in-window items ordered by
 * display_order — no client-side filtering needed.
 */
export function useTickerItems() {
  return useApiFetch<TickerItemsResponse>('/api/ticker-items', {
    key: 'ticker-items',
  })
}

/**
 * Pure resolution rule shared by the layout and its tests: prefer the
 * admin-curated API items; fall back to the hardcoded brand items when the
 * API list is empty or absent (unreachable API, fresh install) so the
 * ticker never renders empty.
 */
export function resolveTickerItems(
  apiItems: TickerItem[] | undefined,
  fallback: TickerDisplayItem[],
): TickerDisplayItem[] {
  if (!apiItems || apiItems.length === 0) {
    return fallback
  }

  return apiItems.map((item) => ({
    label: item.label,
    text: item.text,
    ...(item.href ? { href: item.href } : {}),
  }))
}
