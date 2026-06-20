import type { EventLocation } from '~/types/calendar-event'
import { safeJsonLd } from '~/utils/safeJsonLd'

/**
 * Site-wide social-share image fallback. A page that supplies no `image`
 * inherits this. Lives at `frontend/public/og-default.png` (served at
 * `${siteUrl}/og-default.png`). 1200×630 is the recommended source size.
 */
export const DEFAULT_OG_IMAGE = '/og-default.png'

export interface SeoInput {
  /** BARE page title — no brand suffix. The global titleTemplate adds it. */
  title: string
  description: string
  /** Canonical/og:url path (e.g. `/events/foo`). Defaults to the current route. */
  path?: string
  /** og:image. Absolute URLs pass through; falls back to DEFAULT_OG_IMAGE. */
  image?: string | null
  /** og:type. Defaults to `website`. */
  type?: string
  /** Structured data — one object or an array; emitted via safeJsonLd. */
  jsonLd?: Record<string, unknown> | Array<Record<string, unknown>> | null
  noindex?: boolean
}

/**
 * Resolve a path or URL to an absolute URL against `siteUrl`.
 * - `http(s)://…` is passed through unchanged.
 * - A site-relative path is prefixed with `siteUrl` (single separating slash).
 * - Returns `undefined` when `siteUrl` is empty, so callers can conditionally
 *   omit the tag (mirrors the existing `...(siteUrl ? […] : [])` page idiom).
 */
export function absoluteUrl(
  pathOrUrl: string | null | undefined,
  siteUrl: string,
): string | undefined {
  if (!pathOrUrl) return undefined
  if (/^https?:\/\//i.test(pathOrUrl)) return pathOrUrl
  const base = siteUrl.replace(/\/$/, '')
  if (!base) return undefined
  const path = pathOrUrl.startsWith('/') ? pathOrUrl : `/${pathOrUrl}`
  return `${base}${path}`
}

/** Site-wide Organization schema. Emitted once globally from `app.vue`. */
export function organizationSchema(siteUrl: string): Record<string, unknown> {
  const base = siteUrl.replace(/\/$/, '')
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Final Cut',
    ...(base ? { url: base } : {}),
    ...(base ? { logo: `${base}/android-chrome-512x512.png` } : {}),
  }
}

export interface EventSchemaInput {
  name: string
  startDate?: string | null
  endDate?: string | null
  description?: string | null
  /** Absolute image URL (event images are already CDN-absolute). */
  image?: string | null
  /** Absolute canonical URL for the event. */
  url?: string
  ticketUrl?: string | null
  location?: EventLocation | null
  /** Used for the organizer URL. */
  siteUrl: string
}

/**
 * schema.org Event built from real CalendarEvent fields only — every optional
 * field is omitted when its source is null so we never assert data we lack.
 * Google requires name + startDate + location for Event rich results.
 */
export function eventSchema(input: EventSchemaInput): Record<string, unknown> {
  const base = input.siteUrl.replace(/\/$/, '')
  const schema: Record<string, unknown> = {
    '@context': 'https://schema.org',
    '@type': 'Event',
    name: input.name,
    eventStatus: 'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
    organizer: {
      '@type': 'Organization',
      name: 'Final Cut',
      ...(base ? { url: base } : {}),
    },
  }

  if (input.startDate) schema.startDate = input.startDate
  if (input.endDate) schema.endDate = input.endDate
  if (input.description) schema.description = input.description
  if (input.image) schema.image = input.image
  if (input.url) schema.url = input.url

  if (input.ticketUrl) {
    schema.offers = {
      '@type': 'Offer',
      url: input.ticketUrl,
      availability: 'https://schema.org/InStock',
    }
  }

  const loc = input.location
  if (loc) {
    schema.location = {
      '@type': 'Place',
      name: loc.name,
      address: {
        '@type': 'PostalAddress',
        ...(loc.street ? { streetAddress: loc.street } : {}),
        ...(loc.city ? { addressLocality: loc.city } : {}),
        ...(loc.state ? { addressRegion: loc.state } : {}),
        ...(loc.postalCode ? { postalCode: loc.postalCode } : {}),
        ...(loc.country ? { addressCountry: loc.country } : {}),
      },
      ...(loc.latitude != null && loc.longitude != null
        ? {
            geo: {
              '@type': 'GeoCoordinates',
              latitude: Number(loc.latitude),
              longitude: Number(loc.longitude),
            },
          }
        : {}),
    }
  }

  return schema
}

export interface SeoHeadMeta {
  name?: string
  property?: string
  content: string
}

export interface SeoHead {
  title: string
  meta: SeoHeadMeta[]
  link: Array<{ rel: string; href: string }>
  script: Array<{ type: string; innerHTML: string }>
}

/**
 * Pure builder turning a SeoInput into a `useHead`-shaped object. Kept free of
 * Nuxt imports so it is exhaustively unit-testable (the composable `useSeo` is
 * a thin reactive wrapper over this — same split as the sitemap builder vs its
 * Nitro route). Title stays BARE; the global titleTemplate brands it.
 */
export function buildSeoHead(input: SeoInput, siteUrl: string, currentPath: string): SeoHead {
  const canonical = absoluteUrl(input.path ?? currentPath, siteUrl)
  const image = absoluteUrl(input.image || DEFAULT_OG_IMAGE, siteUrl)
  const type = input.type ?? 'website'

  const meta: SeoHeadMeta[] = [
    { name: 'description', content: input.description },
    { property: 'og:title', content: input.title },
    { property: 'og:description', content: input.description },
    { property: 'og:type', content: type },
    { name: 'twitter:title', content: input.title },
    { name: 'twitter:description', content: input.description },
  ]
  if (canonical) meta.push({ property: 'og:url', content: canonical })
  if (image) {
    meta.push({ property: 'og:image', content: image })
    meta.push({ name: 'twitter:image', content: image })
  }
  if (input.noindex) meta.push({ name: 'robots', content: 'noindex' })

  const link = canonical ? [{ rel: 'canonical', href: canonical }] : []

  const script: SeoHead['script'] = []
  if (input.jsonLd) {
    const blocks = Array.isArray(input.jsonLd) ? input.jsonLd : [input.jsonLd]
    for (const block of blocks) {
      script.push({ type: 'application/ld+json', innerHTML: safeJsonLd(block) })
    }
  }

  return { title: input.title, meta, link, script }
}
