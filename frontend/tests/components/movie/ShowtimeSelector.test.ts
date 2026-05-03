import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ShowtimeSelector from '~/components/movie/ShowtimeSelector.vue'
import type { Showtime } from '~/types/showtime'

// ─── Geolocation mock ─────────────────────────────────────────────────────────
// The component calls useGeolocation() which reads from navigator via the
// composable. We mock the composable module to control status/coords/distanceTo.

const mockGeoStatus = ref<'idle' | 'requesting' | 'granted' | 'denied' | 'unsupported'>('idle')
const mockGeoCoords = ref<{ latitude: number; longitude: number } | null>(null)
const mockDistanceTo = vi.fn((_loc: { latitude: number | null; longitude: number | null }) => null as number | null)

vi.mock('~/composables/useGeolocation', () => ({
  useGeolocation: () => ({
    status: mockGeoStatus,
    coords: mockGeoCoords,
    error: ref(null),
    request: vi.fn(),
    distanceTo: mockDistanceTo,
  }),
}))

// ─── Fixture builders ─────────────────────────────────────────────────────────

function makeShowtime(overrides: Partial<Showtime> = {}): Showtime {
  return {
    id: 'st-1',
    movieId: 1,
    movieSlug: 'test-movie',
    movieTitle: 'Test',
    screenId: 's1',
    screenName: 'Screen 1',
    startTime: '2026-04-07T19:00:00Z',
    endTime: '2026-04-07T21:00:00Z',
    priceStandard: 1500,
    pricePremium: 2000,
    priceAccessible: 1200,
    location: {
      slug: 'downtown',
      name: 'Downtown',
      latitude: 40.7128,
      longitude: -74.0060,
    },
    ...overrides,
  }
}

const DOWNTOWN = { slug: 'downtown', name: 'Downtown', latitude: 40.7128, longitude: -74.0060 }
const UPTOWN = { slug: 'uptown', name: 'Uptown', latitude: 40.7831, longitude: -73.9712 }

describe('ShowtimeSelector', () => {
  beforeEach(() => {
    mockGeoStatus.value = 'idle'
    mockGeoCoords.value = null
    mockDistanceTo.mockReturnValue(null)
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // ── Empty state ──────────────────────────────────────────────────────────

  it('shows the "Showtimes coming soon" empty state when there are no showtimes', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [] },
    })
    expect(wrapper.text()).toContain('Showtimes coming soon')
  })

  it('renders the "Notify Me" CTA in the empty state when movieSlug is provided', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes: [], movieSlug: 'test-movie' },
    })
    const link = wrapper.find('.showtime-selector__notify-link')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toContain('test-movie')
  })

  // ── Venue group rendering ────────────────────────────────────────────────

  it('renders one venue heading per location', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
      makeShowtime({ id: 'st-2', location: UPTOWN, startTime: '2026-04-07T20:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const headings = wrapper.findAll('.showtime-selector__venue-name')
    const names = headings.map(h => h.text())
    expect(names).toContain('Downtown')
    expect(names).toContain('Uptown')
  })

  it('renders venues alphabetically when geolocation is not granted', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', location: UPTOWN, startTime: '2026-04-07T19:00:00Z' }),
      makeShowtime({ id: 'st-2', location: DOWNTOWN, startTime: '2026-04-07T20:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const headings = wrapper.findAll('.showtime-selector__venue-name').map(h => h.text())
    expect(headings[0]).toBe('Downtown')
    expect(headings[1]).toBe('Uptown')
  })

  it('expands all venue groups when geolocation is not granted', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
      makeShowtime({ id: 'st-2', location: UPTOWN, startTime: '2026-04-07T20:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const openPanels = wrapper.findAll('.showtime-selector__venue-times--open')
    expect(openPanels).toHaveLength(2)
  })

  it('only renders venues that have showtimes on the selected date', async () => {
    // Downtown has a showtime today; Uptown has none — should not render at all.
    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const headings = wrapper.findAll('.showtime-selector__venue-name').map(h => h.text())
    expect(headings).toContain('Downtown')
    expect(headings).not.toContain('Uptown')
  })

  // ── Geolocation granted path ─────────────────────────────────────────────

  it('expands only the closest venue group when geolocation is granted', async () => {
    // Mock: Downtown is 1 mi away, Uptown is 5 mi away
    mockGeoStatus.value = 'granted'
    mockDistanceTo.mockImplementation((loc) => {
      if (loc.latitude === DOWNTOWN.latitude) return 1.0
      if (loc.latitude === UPTOWN.latitude) return 5.0
      return null
    })

    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
      makeShowtime({ id: 'st-2', location: UPTOWN, startTime: '2026-04-07T20:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })

    // Closest (Downtown, distance 1.0) should be the first open group.
    const venues = wrapper.findAll('.showtime-selector__venue')
    expect(venues).toHaveLength(2)

    // First venue should be Downtown (sorted by distance).
    const firstHeading = venues[0].find('.showtime-selector__venue-name')
    expect(firstHeading.text()).toBe('Downtown')

    // First group should be open, second should be closed.
    expect(venues[0].find('.showtime-selector__venue-times').classes()).toContain('showtime-selector__venue-times--open')
    expect(venues[1].find('.showtime-selector__venue-times').classes()).not.toContain('showtime-selector__venue-times--open')
  })

  it('renders distance captions when geolocation is granted', async () => {
    mockGeoStatus.value = 'granted'
    mockDistanceTo.mockImplementation((loc) => {
      if (loc.latitude === DOWNTOWN.latitude) return 2.3
      return null
    })

    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })

    const distEl = wrapper.find('.showtime-selector__venue-dist')
    expect(distEl.exists()).toBe(true)
    expect(distEl.text()).toContain('2.3 mi away')
  })

  it('does not render distance captions when geolocation is not granted', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const distEl = wrapper.find('.showtime-selector__venue-dist')
    expect(distEl.exists()).toBe(false)
  })

  // ── Time slot routing ────────────────────────────────────────────────────

  it('each time slot links to /purchase/:showtimeId with ?loc=<venue-slug>', async () => {
    // The ?loc= query carries the venue slug forward to the seat-picker so
    // a direct visit can bootstrap activeLocation without depending on a
    // previously-restored localStorage value (Codex review fix).
    const showtimes = [
      makeShowtime({ id: 'st-99', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const slot = wrapper.find('.showtime-selector__slot')
    expect(slot.exists()).toBe(true)
    expect(slot.attributes('href')).toBe('/purchase/st-99?loc=downtown')
  })

  // ── Venue toggle ─────────────────────────────────────────────────────────

  it('clicking the venue header toggles the group open/closed', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })

    const header = wrapper.find('.showtime-selector__venue-hd')
    // Initially open (no geolocation)
    expect(wrapper.find('.showtime-selector__venue-times').classes()).toContain('showtime-selector__venue-times--open')
    await header.trigger('click')
    expect(wrapper.find('.showtime-selector__venue-times').classes()).not.toContain('showtime-selector__venue-times--open')
    await header.trigger('click')
    expect(wrapper.find('.showtime-selector__venue-times').classes()).toContain('showtime-selector__venue-times--open')
  })

  it('venue header has aria-expanded reflecting the open state', async () => {
    const showtimes = [
      makeShowtime({ id: 'st-1', location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' }),
    ]
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: { showtimes },
    })
    const header = wrapper.find('.showtime-selector__venue-hd')
    expect(header.attributes('aria-expanded')).toBe('true')
    await header.trigger('click')
    expect(header.attributes('aria-expanded')).toBe('false')
  })

  // ── Date strip ───────────────────────────────────────────────────────────

  it('renders a date strip with 7 day buttons', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: {
        showtimes: [makeShowtime({ location: DOWNTOWN, startTime: '2026-04-07T19:00:00Z' })],
      },
    })
    const days = wrapper.findAll('.showtime-selector__day')
    expect(days).toHaveLength(7)
  })

  it('date strip uses tablist role with a descriptive aria-label', async () => {
    const wrapper = await mountSuspended(ShowtimeSelector, {
      props: {
        showtimes: [makeShowtime({ location: DOWNTOWN })],
      },
    })
    const tablist = wrapper.find('[role="tablist"]')
    expect(tablist.exists()).toBe(true)
    expect(tablist.attributes('aria-label')).toBe('Showtime dates')
  })
})
