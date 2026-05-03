/**
 * Dynamic URL source for @nuxtjs/sitemap.
 *
 * Returns URL entries for every public dynamic route: movie slugs, calendar
 * event slugs, location slugs, and blog post slugs. The sitemap module merges
 * these with the static routes it discovers from routeRules and the page tree.
 *
 * Naming convention: @nuxtjs/sitemap looks for sources configured in
 * `sitemap.sources`. The conventional internal source is this file's path
 * (/api/__sitemap__/urls), but any server route can serve as a source.
 *
 * Cache behaviour: each backend API call carries its own HTTP-level caching
 * (Cache-Control: public, max-age=300 from the Laravel API). This handler
 * itself is not cached — it runs fresh on each sitemap revalidation tick.
 * The sitemap module applies its own ISR-aligned caching on the /sitemap.xml
 * output; individual handler freshness is deliberate here.
 *
 * Blog posts come from the static TypeScript data file because the project
 * does not yet use @nuxt/content — blog posts are authored in ~/data/blog.ts.
 * When/if the project migrates to @nuxt/content, replace the import below
 * with a queryCollection call.
 */

import { defineEventHandler } from 'h3'
import { useRuntimeConfig } from '#imports'
import { blogPosts } from '~/data/blog'

// Mirrors the shape @nuxtjs/sitemap accepts from a server source. Defining
// locally because the module's runtime types are not in its public exports map.
interface SitemapUrlInput {
  loc: string
  lastmod?: string
  changefreq?: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never'
  priority?: number
}

interface MovieItem {
  slug: string
  updated_at?: string
}

interface EventItem {
  slug: string | null
  updated_at?: string
  type: string
}

interface LocationItem {
  slug: string
  updated_at?: string
}

interface ApiResponse<T> {
  data: T[]
  meta?: {
    total: number
    page: number
    per_page: number
  }
}

export default defineEventHandler(async (event): Promise<SitemapUrlInput[]> => {
  // Resolve the backend API base URL from runtime config.
  // The `useRuntimeConfig` auto-import is available in Nitro server context.
  const config = useRuntimeConfig(event)
  const apiBase = (config.public.apiBaseUrl as string | undefined)?.trim() ?? ''

  // Build an absolute base URL for $fetch to reach the Laravel backend.
  // In production, apiBase is the Docker-internal address (e.g. http://backend).
  // In local dev without an explicit config, we fall back to localhost so
  // developer machines can generate the sitemap without the full Docker stack.
  const fetchBase = apiBase || 'http://localhost:8000'

  const urls: SitemapUrlInput[] = []

  // Fan out the three backend reads in parallel — they are independent and
  // each is a separate HTTP round-trip. Sequential awaits would triple the
  // sitemap revalidation latency.
  const [moviesResult, eventsResult, locationsResult] = await Promise.allSettled([
    $fetch<ApiResponse<MovieItem>>('/api/movies', {
      baseURL: fetchBase,
      headers: { Accept: 'application/json' },
      query: { per_page: 500 },
    }),
    $fetch<ApiResponse<EventItem>>('/api/calendar/events', {
      baseURL: fetchBase,
      headers: { Accept: 'application/json' },
      query: { per_page: 500 },
    }),
    $fetch<ApiResponse<LocationItem>>('/api/locations', {
      baseURL: fetchBase,
      headers: { Accept: 'application/json' },
    }),
  ])

  if (moviesResult.status === 'fulfilled') {
    for (const movie of moviesResult.value.data ?? []) {
      urls.push({
        loc: `/movies/${movie.slug}`,
        lastmod: movie.updated_at,
        changefreq: 'weekly',
        priority: 0.8,
      })
    }
  } else {
    console.error('[sitemap] Failed to fetch movies:', moviesResult.reason)
  }

  // Only events with a non-null slug are linkable detail pages.
  // Showtime-type entries link to their movie detail page (already above).
  if (eventsResult.status === 'fulfilled') {
    for (const event of eventsResult.value.data ?? []) {
      if (!event.slug || event.type === 'showtime') continue
      urls.push({
        loc: `/events/${event.slug}`,
        lastmod: event.updated_at,
        changefreq: 'weekly',
        priority: 0.7,
      })
    }
  } else {
    console.error('[sitemap] Failed to fetch calendar events:', eventsResult.reason)
  }

  if (locationsResult.status === 'fulfilled') {
    for (const location of locationsResult.value.data ?? []) {
      urls.push({
        loc: `/locations/${location.slug}`,
        lastmod: location.updated_at,
        changefreq: 'monthly',
        priority: 0.7,
      })
    }
  } else {
    console.error('[sitemap] Failed to fetch locations:', locationsResult.reason)
  }

  // ── Blog posts ───────────────────────────────────────────────────────────
  // Blog data is static TypeScript — no API call needed. The `date` field
  // is used as the lastmod since static content has no `updated_at`.
  for (const post of blogPosts) {
    urls.push({
      loc: `/blog/${post.slug}`,
      lastmod: post.date,
      changefreq: 'monthly',
      priority: 0.6,
    })
  }

  return urls
})
