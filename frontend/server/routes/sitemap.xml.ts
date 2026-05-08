import { defineEventHandler } from 'h3'
import { useRuntimeConfig } from '#imports'
import { buildSitemapXml, type SitemapEntry } from '../utils/sitemap-builder'

const STATIC_ENTRIES: SitemapEntry[] = [
  { loc: '/' },
  { loc: '/movies' },
  { loc: '/whats-on' },
  { loc: '/events' },
  { loc: '/food-drink' },
  { loc: '/locations' },
  { loc: '/faq' },
  { loc: '/contact' },
  { loc: '/accessibility' },
  { loc: '/careers' },
  { loc: '/gift-cards' },
  { loc: '/gift-cards/bulk' },
  { loc: '/private-screenings' },
  { loc: '/blog' },
]

const EXCLUDED_PREFIXES = ['/purchase/', '/account', '/auth/'] as const

function isExcluded(loc: string): boolean {
  return EXCLUDED_PREFIXES.some((prefix) => loc === prefix || loc.startsWith(prefix))
}

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const siteUrl = (config.public.siteUrl as string) || 'https://finalcut.test'

  // Dynamic URLs come from the existing Nitro endpoint that the architecture
  // doc designates as the sitemap source. It returns Array<SitemapEntry>.
  const dynamic = await $fetch<SitemapEntry[]>('/api/__sitemap__/urls', {
    headers: { accept: 'application/json' },
  }).catch(() => [] as SitemapEntry[])

  const entries = [...STATIC_ENTRIES, ...dynamic].filter((e) => !isExcluded(e.loc))
  const xml = buildSitemapXml(siteUrl, entries)

  // Use the underlying Node response directly — the auto-imported `setHeader`
  // resolves to h3@2 in dev (pulled in by @nuxt/test-utils) but the runtime
  // event is h3@1 shape, causing a setResponseHeader crash.
  event.node.res.setHeader('content-type', 'application/xml; charset=utf-8')
  event.node.res.setHeader('cache-control', 'public, max-age=900, s-maxage=900')
  return xml
})
