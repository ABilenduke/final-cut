import { describe, it, expect } from 'vitest'

// We test the pure builder, not the Nitro route — the route is a thin wrapper.
import { buildSitemapXml } from '../../server/utils/sitemap-builder'

describe('buildSitemapXml', () => {
  const SITE_URL = 'https://finalcut.test'

  it('emits valid XML with the urlset preamble', () => {
    const xml = buildSitemapXml(SITE_URL, [{ loc: '/', lastmod: '2026-05-01' }])
    expect(xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>')).toBe(true)
    expect(xml).toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
    expect(xml.endsWith('</urlset>')).toBe(true)
  })

  it('prefixes relative locs with the site URL and trims trailing slashes', () => {
    const xml = buildSitemapXml('https://finalcut.test/', [{ loc: '/movies' }])
    expect(xml).toContain('<loc>https://finalcut.test/movies</loc>')
    expect(xml).not.toContain('https://finalcut.test//movies')
  })

  it('passes absolute locs through unchanged', () => {
    const xml = buildSitemapXml(SITE_URL, [{ loc: 'https://finalcut.test/movies' }])
    expect(xml).toContain('<loc>https://finalcut.test/movies</loc>')
  })

  it('emits lastmod when present, omits when absent', () => {
    const xml = buildSitemapXml(SITE_URL, [
      { loc: '/', lastmod: '2026-05-01' },
      { loc: '/contact' },
    ])
    expect(xml).toContain('<lastmod>2026-05-01</lastmod>')
    // /contact entry must not have a lastmod tag
    const contactEntry = xml.split('<url>').find((s) => s.includes('/contact'))
    expect(contactEntry).toBeDefined()
    expect(contactEntry).not.toContain('<lastmod>')
  })

  it('escapes XML-special characters in loc values', () => {
    const xml = buildSitemapXml(SITE_URL, [{ loc: '/blog/q&a-with-director' }])
    expect(xml).toContain('<loc>https://finalcut.test/blog/q&amp;a-with-director</loc>')
    expect(xml).not.toContain('q&a-with')
  })

  it('emits the empty <urlset/> when no URLs are supplied', () => {
    const xml = buildSitemapXml(SITE_URL, [])
    expect(xml).toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
    expect(xml).toContain('</urlset>')
  })
})
