/**
 * LocationFilterChips component tests.
 *
 * Verifies:
 * - One chip rendered per location plus "All Locations"
 * - "All Locations" chip is active (aria-pressed=true) when no ?location= query param
 * - Location chip is active when matching ?location= query
 * - Clicking a location chip calls router.push with the correct ?location= slug
 * - Clicking "All Locations" removes ?location= from the query
 * - Post-hydration suggestion chip renders when useGeolocation is granted + no filter set
 * - Suggestion chip does NOT render without granted geolocation
 * - Suggestion chip does NOT render when ?location= is already set
 *
 * Route/router mocking: uses mountSuspended's `route` option (from @nuxt/test-utils)
 * to set the initial query. Router.push is mocked at the composable call site to
 * capture the argument without a live navigation.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import LocationFilterChips from '~/components/movie/LocationFilterChips.vue'
import type { Location } from '~/types/location'

// ── Mock useGeolocation ───────────────────────────────────────────────────
vi.mock('~/composables/useGeolocation', () => ({
  useGeolocation: vi.fn(),
}))

import { useGeolocation } from '~/composables/useGeolocation'
const mockUseGeolocation = vi.mocked(useGeolocation)

// ── Capture router.push via vi.spyOn on the Nuxt router instance ─────────
// In the Nuxt test environment, useRouter() returns the actual Nuxt router.
// We spy on `push` after mounting so the spy wraps the real implementation.
// `mockRouterPush` is re-assigned per test in beforeEach.
let mockRouterPush: ReturnType<typeof vi.fn>

// Import useRouter from the Nuxt composable resolution path
import { useRouter } from '#app'

// ── Test fixtures ─────────────────────────────────────────────────────────

function makeLocation(overrides: Partial<Location> = {}): Location {
  return {
    id: 'loc-1',
    slug: 'downtown',
    name: 'Downtown',
    street: '123 Main St',
    city: 'Anytown',
    state: 'NY',
    postal_code: '10001',
    country: 'US',
    phone: '555-0100',
    email: 'downtown@finalcut.test',
    latitude: 40.7128,
    longitude: -74.006,
    timezone: 'America/New_York',
    hours: {},
    address: '123 Main St, Anytown, NY 10001',
    imageUrl: null,
    ...overrides,
  }
}

const DOWNTOWN = makeLocation({ id: 'loc-1', slug: 'downtown', name: 'Downtown', latitude: 40.7128, longitude: -74.006 })
const UPTOWN = makeLocation({ id: 'loc-2', slug: 'uptown', name: 'Uptown', latitude: 40.7549, longitude: -73.984 })
const TEST_LOCATIONS = [DOWNTOWN, UPTOWN]

/** Default geolocation mock: idle (no coords). */
function mockGeoIdle() {
  mockUseGeolocation.mockReturnValue({
    status: ref('idle'),
    coords: ref(null),
    error: ref(null),
    request: vi.fn(),
    distanceTo: vi.fn().mockReturnValue(null),
  } as any)
}

/** Granted geolocation mock: coords near Downtown. */
function mockGeoGranted() {
  const distanceFn = vi.fn((loc: { latitude: number | null; longitude: number | null }) => {
    if (loc.latitude === null || loc.longitude === null) return null
    // Return a smaller distance for Downtown so it's the "closest"
    return loc.latitude === DOWNTOWN.latitude ? 1.5 : 5.2
  })

  mockUseGeolocation.mockReturnValue({
    status: ref('granted'),
    coords: ref({ latitude: 40.72, longitude: -74.01 }),
    error: ref(null),
    request: vi.fn(),
    distanceTo: distanceFn,
  } as any)
}

// ── Tests ─────────────────────────────────────────────────────────────────

describe('LocationFilterChips', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGeoIdle()
    // Spy on the actual Nuxt router's push method
    const router = useRouter()
    mockRouterPush = vi.spyOn(router, 'push').mockImplementation(vi.fn() as any)
  })

  // ── Chip rendering ─────────────────────────────────────────────────────

  it('renders "All Locations" chip plus one chip per location', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    // Exclude suggestion chip from count
    const allButtons = wrapper.findAll('button.location-chips__chip')
    const nonSuggestion = allButtons.filter(b => !b.classes('location-chips__chip--suggestion'))
    expect(nonSuggestion).toHaveLength(3) // All Locations + Downtown + Uptown
    expect(nonSuggestion[0].text()).toBe('All Locations')
    expect(nonSuggestion[1].text()).toBe('Downtown')
    expect(nonSuggestion[2].text()).toBe('Uptown')
  })

  it('renders only "All Locations" when locations array is empty', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: [] },
      route: '/movies',
    })

    const buttons = wrapper.findAll('button.location-chips__chip')
    expect(buttons).toHaveLength(1)
    expect(buttons[0].text()).toBe('All Locations')
  })

  // ── Active state: no ?location= ────────────────────────────────────────

  it('"All Locations" chip has aria-pressed=true when no ?location= query', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    const allChip = wrapper.find('button.location-chips__chip:first-child')
    expect(allChip.attributes('aria-pressed')).toBe('true')
  })

  // ── Active state: with ?location= ─────────────────────────────────────

  it('location chip has aria-pressed=true when matching ?location= query', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies?location=downtown',
    })

    const buttons = wrapper.findAll('button.location-chips__chip')
    const nonSuggestion = buttons.filter(b => !b.classes('location-chips__chip--suggestion'))

    // "All Locations" should be inactive
    expect(nonSuggestion[0].attributes('aria-pressed')).toBe('false')
    // "Downtown" should be active
    expect(nonSuggestion[1].attributes('aria-pressed')).toBe('true')
    // "Uptown" should be inactive
    expect(nonSuggestion[2].attributes('aria-pressed')).toBe('false')
  })

  it('"All Locations" chip has aria-pressed=false when a location is active', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies?location=uptown',
    })

    const allChip = wrapper.find('button.location-chips__chip:first-child')
    expect(allChip.attributes('aria-pressed')).toBe('false')
  })

  // ── Click → router.push ────────────────────────────────────────────────

  it('clicking a location chip calls router.push with ?location=<slug>', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    const buttons = wrapper.findAll('button.location-chips__chip')
    const nonSuggestion = buttons.filter(b => !b.classes('location-chips__chip--suggestion'))
    await nonSuggestion[1].trigger('click') // Downtown

    expect(mockRouterPush).toHaveBeenCalledOnce()
    const call = mockRouterPush.mock.calls[0][0]
    expect(call.query.location).toBe('downtown')
  })

  it('clicking "All Locations" calls router.push without ?location=', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies?location=downtown',
    })

    const allChip = wrapper.find('button.location-chips__chip:first-child')
    await allChip.trigger('click')

    expect(mockRouterPush).toHaveBeenCalledOnce()
    const call = mockRouterPush.mock.calls[0][0]
    expect(call.query.location).toBeUndefined()
  })

  it('clicking a chip preserves other existing query params', async () => {
    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies?status=coming_soon',
    })

    const buttons = wrapper.findAll('button.location-chips__chip')
    const nonSuggestion = buttons.filter(b => !b.classes('location-chips__chip--suggestion'))
    await nonSuggestion[2].trigger('click') // Uptown

    expect(mockRouterPush).toHaveBeenCalledOnce()
    const call = mockRouterPush.mock.calls[0][0]
    expect(call.query.status).toBe('coming_soon')
    expect(call.query.location).toBe('uptown')
  })

  // ── Geolocation suggestion chip ────────────────────────────────────────

  it('suggestion chip renders when geolocation is granted and no filter is set', async () => {
    mockGeoGranted()

    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    const suggestion = wrapper.find('.location-chips__chip--suggestion')
    expect(suggestion.exists()).toBe(true)
    expect(suggestion.text()).toContain('Filter to nearest:')
    expect(suggestion.text()).toContain('Downtown')
  })

  it('suggestion chip does NOT render when geolocation is idle', async () => {
    mockGeoIdle()

    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    expect(wrapper.find('.location-chips__chip--suggestion').exists()).toBe(false)
  })

  it('suggestion chip does NOT render when geolocation is denied', async () => {
    mockUseGeolocation.mockReturnValue({
      status: ref('denied'),
      coords: ref(null),
      error: ref('Permission denied.'),
      request: vi.fn(),
      distanceTo: vi.fn().mockReturnValue(null),
    } as any)

    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    expect(wrapper.find('.location-chips__chip--suggestion').exists()).toBe(false)
  })

  it('suggestion chip does NOT render when ?location= is already set', async () => {
    mockGeoGranted()

    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies?location=downtown',
    })

    expect(wrapper.find('.location-chips__chip--suggestion').exists()).toBe(false)
  })

  it('clicking the suggestion chip calls router.push with the closest location slug', async () => {
    mockGeoGranted()

    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    const suggestion = wrapper.find('.location-chips__chip--suggestion')
    await suggestion.trigger('click')

    expect(mockRouterPush).toHaveBeenCalledOnce()
    const call = mockRouterPush.mock.calls[0][0]
    expect(call.query.location).toBe('downtown')
  })

  it('suggestion chip has descriptive aria-label', async () => {
    mockGeoGranted()

    const wrapper = await mountSuspended(LocationFilterChips, {
      props: { locations: TEST_LOCATIONS },
      route: '/movies',
    })

    const suggestion = wrapper.find('.location-chips__chip--suggestion')
    expect(suggestion.attributes('aria-label')).toContain('Downtown')
  })
})
