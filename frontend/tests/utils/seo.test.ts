import { describe, it, expect } from 'vitest'
import {
  absoluteUrl,
  organizationSchema,
  eventSchema,
  buildSeoHead,
  DEFAULT_OG_IMAGE,
} from '~/utils/seo'
import type { EventLocation } from '~/types/calendar-event'

const SITE = 'https://finalcut.test'

describe('absoluteUrl', () => {
  it('passes absolute http(s) URLs through unchanged', () => {
    expect(absoluteUrl('https://cdn.example.com/a.png', SITE)).toBe('https://cdn.example.com/a.png')
    expect(absoluteUrl('http://example.com/b.png', SITE)).toBe('http://example.com/b.png')
  })

  it('prefixes site-relative paths with siteUrl', () => {
    expect(absoluteUrl('/events/gala', SITE)).toBe('https://finalcut.test/events/gala')
  })

  it('adds a leading slash to bare relative paths', () => {
    expect(absoluteUrl('og-default.png', SITE)).toBe('https://finalcut.test/og-default.png')
  })

  it('trims a trailing slash on siteUrl', () => {
    expect(absoluteUrl('/x', 'https://finalcut.test/')).toBe('https://finalcut.test/x')
  })

  it('returns undefined for empty input or empty siteUrl', () => {
    expect(absoluteUrl(null, SITE)).toBeUndefined()
    expect(absoluteUrl('', SITE)).toBeUndefined()
    expect(absoluteUrl('/x', '')).toBeUndefined()
  })
})

describe('organizationSchema', () => {
  it('builds an Organization with url + logo when siteUrl is set', () => {
    expect(organizationSchema(SITE)).toEqual({
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: 'Final Cut',
      url: 'https://finalcut.test',
      logo: 'https://finalcut.test/android-chrome-512x512.png',
    })
  })

  it('omits url + logo when siteUrl is empty', () => {
    expect(organizationSchema('')).toEqual({
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: 'Final Cut',
    })
  })
})

describe('eventSchema', () => {
  const fullLocation: EventLocation = {
    name: 'Downtown Cinema',
    street: '123 Marquee Ave',
    city: 'Portland',
    state: 'OR',
    postalCode: '97201',
    country: 'US',
    latitude: '45.523064',
    longitude: '-122.676483',
  }

  it('builds a complete Event from all available fields', () => {
    const schema = eventSchema({
      name: 'Opening Night Gala',
      startDate: '2026-06-15T19:00:00-07:00',
      endDate: '2026-06-15T21:00:00-07:00',
      description: 'A black-tie premiere.',
      image: 'https://cdn.example.com/gala.jpg',
      url: 'https://finalcut.test/events/gala',
      ticketUrl: 'https://tickets.example.com/gala',
      location: fullLocation,
      siteUrl: SITE,
    })

    expect(schema['@type']).toBe('Event')
    expect(schema.name).toBe('Opening Night Gala')
    expect(schema.startDate).toBe('2026-06-15T19:00:00-07:00')
    expect(schema.endDate).toBe('2026-06-15T21:00:00-07:00')
    expect(schema.eventStatus).toBe('https://schema.org/EventScheduled')
    expect(schema.eventAttendanceMode).toBe('https://schema.org/OfflineEventAttendanceMode')
    expect(schema.image).toBe('https://cdn.example.com/gala.jpg')
    expect(schema.url).toBe('https://finalcut.test/events/gala')
    expect(schema.offers).toEqual({
      '@type': 'Offer',
      url: 'https://tickets.example.com/gala',
      availability: 'https://schema.org/InStock',
    })
    expect(schema.location).toEqual({
      '@type': 'Place',
      name: 'Downtown Cinema',
      address: {
        '@type': 'PostalAddress',
        streetAddress: '123 Marquee Ave',
        addressLocality: 'Portland',
        addressRegion: 'OR',
        postalCode: '97201',
        addressCountry: 'US',
      },
      geo: {
        '@type': 'GeoCoordinates',
        latitude: 45.523064,
        longitude: -122.676483,
      },
    })
    expect(schema.organizer).toEqual({
      '@type': 'Organization',
      name: 'Final Cut',
      url: 'https://finalcut.test',
    })
  })

  it('omits optional fields when their source is null', () => {
    const schema = eventSchema({
      name: 'Minimal Event',
      startDate: '2026-07-01T19:00:00-07:00',
      endDate: null,
      description: null,
      image: null,
      ticketUrl: null,
      location: null,
      siteUrl: SITE,
    })

    expect(schema.name).toBe('Minimal Event')
    expect(schema.startDate).toBe('2026-07-01T19:00:00-07:00')
    expect(schema).not.toHaveProperty('endDate')
    expect(schema).not.toHaveProperty('description')
    expect(schema).not.toHaveProperty('image')
    expect(schema).not.toHaveProperty('offers')
    expect(schema).not.toHaveProperty('location')
  })

  it('omits the geo block when coordinates are absent', () => {
    const schema = eventSchema({
      name: 'No Geo',
      location: { ...fullLocation, latitude: null, longitude: null },
      siteUrl: SITE,
    })
    expect(schema.location).toMatchObject({ '@type': 'Place', name: 'Downtown Cinema' })
    expect(schema.location).not.toHaveProperty('geo')
  })
})

describe('buildSeoHead', () => {
  it('emits a bare title, canonical, og/twitter meta, and default og:image', () => {
    const head = buildSeoHead({ title: 'Events', description: 'All events.' }, SITE, '/events')

    expect(head.title).toBe('Events') // bare — titleTemplate brands it
    expect(head.link).toEqual([{ rel: 'canonical', href: 'https://finalcut.test/events' }])
    expect(head.meta).toContainEqual({ name: 'description', content: 'All events.' })
    expect(head.meta).toContainEqual({ property: 'og:title', content: 'Events' })
    expect(head.meta).toContainEqual({ property: 'og:url', content: 'https://finalcut.test/events' })
    expect(head.meta).toContainEqual({
      property: 'og:image',
      content: `https://finalcut.test${DEFAULT_OG_IMAGE}`,
    })
    expect(head.meta).toContainEqual({
      name: 'twitter:image',
      content: `https://finalcut.test${DEFAULT_OG_IMAGE}`,
    })
  })

  it('prefers an explicit path over the current route for the canonical', () => {
    const head = buildSeoHead(
      { title: 'Gala', description: 'd', path: '/events/gala' },
      SITE,
      '/some/other/route',
    )
    expect(head.link).toEqual([{ rel: 'canonical', href: 'https://finalcut.test/events/gala' }])
  })

  it('uses a provided absolute image instead of the fallback', () => {
    const head = buildSeoHead(
      { title: 'X', description: 'd', image: 'https://cdn.example.com/x.jpg' },
      SITE,
      '/x',
    )
    expect(head.meta).toContainEqual({ property: 'og:image', content: 'https://cdn.example.com/x.jpg' })
  })

  it('serializes jsonLd through safeJsonLd (XSS-safe)', () => {
    const head = buildSeoHead(
      { title: 'X', description: 'd', jsonLd: { '@type': 'Event', name: '</script>' } },
      SITE,
      '/x',
    )
    expect(head.script).toHaveLength(1)
    expect(head.script[0]!.type).toBe('application/ld+json')
    expect(head.script[0]!.innerHTML).toContain('\\u003c')
    expect(head.script[0]!.innerHTML).not.toContain('</script>')
  })

  it('emits one script per entry for an array of jsonLd blocks', () => {
    const head = buildSeoHead(
      { title: 'X', description: 'd', jsonLd: [{ a: 1 }, { b: 2 }] },
      SITE,
      '/x',
    )
    expect(head.script).toHaveLength(2)
  })

  it('adds a noindex robots tag when requested', () => {
    const head = buildSeoHead({ title: 'X', description: 'd', noindex: true }, SITE, '/x')
    expect(head.meta).toContainEqual({ name: 'robots', content: 'noindex' })
  })

  it('omits canonical and og:url when siteUrl is empty', () => {
    const head = buildSeoHead({ title: 'X', description: 'd' }, '', '/x')
    expect(head.link).toEqual([])
    expect(head.meta).not.toContainEqual(expect.objectContaining({ property: 'og:url' }))
  })
})
