import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BookingLocationBanner from '~/components/booking/BookingLocationBanner.vue'
import type { ShowtimeLocation } from '~/types/showtime'

// Full location payload — all optional fields present
const fullLocation: ShowtimeLocation = {
  slug: 'downtown',
  name: 'Final Cut Downtown',
  latitude: 40.7128,
  longitude: -74.006,
  street: '123 Cinema Row',
  city: 'New York',
  state: 'NY',
  postal_code: '10001',
  phone: '(212) 555-0100',
}

// Minimal location — only required fields (seatmap response before backend ships address fields)
const minimalLocation: ShowtimeLocation = {
  slug: 'uptown',
  name: 'Final Cut Uptown',
  latitude: null,
  longitude: null,
}

const movieSlug = 'inception-2010'

describe('BookingLocationBanner', () => {
  it('renders the venue name', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    expect(wrapper.text()).toContain('Final Cut Downtown')
  })

  it('renders the street address', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    expect(wrapper.text()).toContain('123 Cinema Row')
  })

  it('renders the city', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    expect(wrapper.text()).toContain('New York')
  })

  it('renders the phone number', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    expect(wrapper.text()).toContain('(212) 555-0100')
  })

  it('"Change location" link points to /movies/{movieSlug}', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    const link = wrapper.find('a.booking-location-banner__change')
    expect(link.exists()).toBe(true)
    // NuxtLink renders with `to` prop; check the href attribute or the `to` binding
    expect(link.attributes('href')).toBe(`/movies/${movieSlug}`)
  })

  it('"Change location" link text is correct', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    const link = wrapper.find('a.booking-location-banner__change')
    expect(link.text()).toBe('Change location')
  })

  it('does not render address when optional fields are absent', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: minimalLocation, movieSlug },
    })
    // Street and city not present — address block should be absent
    expect(wrapper.text()).not.toContain('Cinema Row')
    // Venue name still rendered
    expect(wrapper.text()).toContain('Final Cut Uptown')
  })

  it('does not render phone when optional field is absent', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: minimalLocation, movieSlug },
    })
    // No phone provided — no tel-like text present
    expect(wrapper.text()).not.toContain('555')
  })

  it('renders the "You\'re booking at" label text', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    expect(wrapper.text()).toContain("You're booking at")
  })

  it('renders as an <aside> landmark', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    // mountSuspended wraps the component in a Suspense host (a <div>), so the
    // <aside> root is the first child rather than wrapper.element itself.
    const aside = wrapper.find('aside')
    expect(aside.exists()).toBe(true)
    expect(aside.attributes('aria-label')).toBe('Booking location')
  })

  it('has no inline style attributes on any element', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    // Walk the DOM tree and assert no element carries a style attribute
    const html = wrapper.html()
    // The SVG width/height attributes are HTML attributes (not style="..."), so this
    // assertion targets only the CSS inline style="" pattern.
    // A deliberate string check: style=" is the opening of any inline style declaration.
    expect(html).not.toContain(' style="')
  })

  it('uses design system CSS classes (no raw hex colors in class names)', async () => {
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug },
    })
    // BEM class names follow the booking-location-banner__ prefix pattern
    const inner = wrapper.find('.booking-location-banner__inner')
    expect(inner.exists()).toBe(true)
    const copy = wrapper.find('.booking-location-banner__copy')
    expect(copy.exists()).toBe(true)
  })

  it('change location link uses the correct movie slug', async () => {
    const differentSlug = 'the-dark-knight-2008'
    const wrapper = await mountSuspended(BookingLocationBanner, {
      props: { location: fullLocation, movieSlug: differentSlug },
    })
    const link = wrapper.find('a.booking-location-banner__change')
    expect(link.attributes('href')).toBe(`/movies/${differentSlug}`)
  })
})
