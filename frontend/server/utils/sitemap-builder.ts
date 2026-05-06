export interface SitemapEntry {
  loc: string
  lastmod?: string
}

const XML_ESCAPE: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&apos;',
}

function escapeXml(value: string): string {
  return value.replace(/[&<>"']/g, (ch) => XML_ESCAPE[ch] ?? ch)
}

function absolutize(siteUrl: string, loc: string): string {
  if (/^https?:\/\//i.test(loc)) return loc
  const trimmedSite = siteUrl.replace(/\/+$/, '')
  const path = loc.startsWith('/') ? loc : `/${loc}`
  return `${trimmedSite}${path}`
}

export function buildSitemapXml(siteUrl: string, entries: SitemapEntry[]): string {
  const urls = entries
    .map((entry) => {
      const loc = escapeXml(absolutize(siteUrl, entry.loc))
      const lastmod = entry.lastmod ? `\n    <lastmod>${escapeXml(entry.lastmod)}</lastmod>` : ''
      return `  <url>\n    <loc>${loc}</loc>${lastmod}\n  </url>`
    })
    .join('\n')

  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>`
}
