import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import LocationCard from '~/components/content/LocationCard.vue'
import type { Location } from '~/types/location'

function makeLocation(overrides: Partial<Location> = {}): Location {
  return {
    id: '1',
    slug: 'downtown',
    name: 'Final Cut Downtown',
    street: '123 Cinema Boulevard',
    city: 'New York',
    state: 'NY',
    postal_code: '10001',
    country: 'US',
    phone: '(212) 555-0199',
    email: 'downtown@finalcut.test',
    latitude: 40.7128,
    longitude: -73.9352,
    timezone: 'America/New_York',
    hours: {
      monday: { open: '11:00', close: '23:00' },
      friday: { open: '10:00', close: '01:00' },
    },
    address: '123 Cinema Boulevard, New York, NY 10001',
    imageUrl: null,
    ...overrides,
  }
}

describe('LocationCard', () => {
  it('renders venue name', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    expect(wrapper.find('.location-card__name').text()).toBe('Final Cut Downtown')
  })

  it('renders street address', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    expect(wrapper.text()).toContain('123 Cinema Boulevard')
  })

  it('renders city and state in address', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    expect(wrapper.text()).toContain('New York')
    expect(wrapper.text()).toContain('NY')
  })

  it('renders phone number as tel: link', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    const phoneLink = wrapper.find('.location-card__phone-link')
    expect(phoneLink.exists()).toBe(true)
    expect(phoneLink.attributes('href')).toBe('tel:(212) 555-0199')
    expect(phoneLink.text()).toBe('(212) 555-0199')
  })

  it('does not render phone section when phone is null', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation({ phone: null }) },
    })
    expect(wrapper.find('.location-card__phone').exists()).toBe(false)
  })

  it('renders "See Showtimes" link to /movies?location=<slug>', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    const cta = wrapper.find('.cv-button--primary')
    expect(cta.exists()).toBe(true)
    expect(cta.attributes('href')).toBe('/movies?location=downtown')
    expect(cta.text()).toContain('See Showtimes')
  })

  it('renders "Get Directions" link with correct lat/lng', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    const directionsLink = wrapper.find('.cv-button--tertiary')
    expect(directionsLink.exists()).toBe(true)
    expect(directionsLink.attributes('href')).toBe(
      'https://maps.google.com/?q=40.7128,-73.9352',
    )
  })

  it('does not render "Get Directions" when coordinates are null', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: {
        location: makeLocation({ latitude: null, longitude: null }),
      },
    })
    expect(wrapper.find('.cv-button--tertiary').exists()).toBe(false)
  })

  it('card link navigates to /locations/:slug', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    const link = wrapper.find('.location-card__link')
    expect(link.attributes('href')).toBe('/locations/downtown')
  })

  it('renders image when imageUrl is provided', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: {
        location: makeLocation({ imageUrl: 'https://example.com/photo.jpg' }),
      },
    })
    const img = wrapper.find('.location-card__image')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/photo.jpg')
    expect(img.attributes('alt')).toBe('Final Cut Downtown exterior')
  })

  it('renders placeholder when no imageUrl', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation({ imageUrl: null }) },
    })
    expect(wrapper.find('.location-card__image').exists()).toBe(false)
    expect(wrapper.find('.location-card__image-placeholder').exists()).toBe(true)
  })

  it('renders distance label when provided', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: {
        location: makeLocation(),
        distanceLabel: '2.3 mi away',
      },
    })
    expect(wrapper.text()).toContain('2.3 mi away')
  })

  it('does not render distance label when not provided', async () => {
    const wrapper = await mountSuspended(LocationCard, {
      props: { location: makeLocation() },
    })
    expect(wrapper.find('.location-card__distance').exists()).toBe(false)
  })
})
