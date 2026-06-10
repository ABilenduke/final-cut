// app/types/ticker-item.ts

/** One admin-curated Neural Ticker entry from GET /api/ticker-items. */
export interface TickerItem {
  id: string
  label: string
  text: string
  href: string | null
}

export interface TickerItemsResponse {
  data: TickerItem[]
}
