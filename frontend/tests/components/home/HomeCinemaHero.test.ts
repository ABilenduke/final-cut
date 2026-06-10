import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeCinemaHero from '~/components/home/HomeCinemaHero.vue'
import type { Movie } from '~/types/movie'
import type { Showtime } from '~/types/showtime'

// The hero side panel shows the featured movie's real upcoming showtimes
// (admin-v2 Plan 16) — mock the API transport with path-keyed fixtures.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'

const mockUseApiFetch = vi.mocked(useApiFetch)

function makeMovie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'blade-runner-2049',
    title: 'Blade Runner 2049',
    tagline: 'The key to the future is finally unearthed.',
    synopsis: 'A young blade runner discovers a long-buried secret.',
    runtime: 164,
    rating: 8.0,
    releaseDate: '2017-10-06',
    genres: [{ id: 878, name: 'Science Fiction' }],
    cast: [],
    posterUrl: 'https://image.tmdb.org/t/p/w500/poster.jpg',
    backdropUrl: 'https://image.tmdb.org/t/p/w1280/backdrop.jpg',
    trailerKey: 'abc123',
    status: 'now_showing',
    ...overrides,
  }
}

function makeShowtime(id: string, startTime: string): Showtime {
  return {
    id,
    movieId: 1,
    movieSlug: 'blade-runner-2049',
    movieTitle: 'Blade Runner 2049',
    screenId: 'aud-1',
    screenName: 'Screen 1',
    startTime,
    endTime: startTime,
    priceStandard: 1850,
    pricePremium: 2400,
    priceAccessible: 1850,
    location: { slug: 'downtown', name: 'Downtown', latitude: null, longitude: null },
  } as Showtime
}

function mockShowtimes(showtimes: Showtime[] | null, pending = false) {
  mockUseApiFetch.mockImplementation(((path: string) => {
    if (path === '/api/movies/blade-runner-2049/showtimes') {
      return {
        data: ref(showtimes === null ? null : { data: showtimes }),
        pending: ref(pending),
        error: ref(null),
        refresh: vi.fn(),
      }
    }
    throw new Error(`Unexpected fetch: ${path}`)
  }) as any)
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('HomeCinemaHero', () => {
  it('renders the film title and tagline', async () => {
    mockShowtimes([])
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('.cinema-hero__title').text()).toBe('Blade Runner 2049')
    expect(wrapper.find('.cinema-hero__sub').text()).toBe('The key to the future is finally unearthed.')
  })

  it('renders a chip per upcoming showtime linking into the purchase flow', async () => {
    mockShowtimes([
      makeShowtime('st-1', '2026-06-10T23:30:00Z'), // 7:30 PM New York
      makeShowtime('st-2', '2026-06-11T01:15:00Z'), // 9:15 PM New York
    ])
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })

    const chips = wrapper.findAll('a.cinema-hero__time')
    expect(chips).toHaveLength(2)
    expect(chips[0]!.attributes('href')).toBe('/purchase/st-1?loc=downtown')
    expect(chips[0]!.text()).toContain('7:30')
    expect(chips[0]!.text()).toContain('PM')
  })

  it('caps the panel at eight chips', async () => {
    mockShowtimes(
      Array.from({ length: 12 }, (_, i) =>
        makeShowtime(`st-${i}`, `2026-06-10T1${i % 10}:00:00Z`),
      ),
    )
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.findAll('.cinema-hero__time')).toHaveLength(8)
  })

  it('renders a quiet empty note instead of chips when there are no showtimes', async () => {
    mockShowtimes([])
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.findAll('.cinema-hero__time')).toHaveLength(0)
    expect(wrapper.find('.cinema-hero__times-empty').exists()).toBe(true)
    // Panel chrome and calendar link survive the empty state.
    expect(wrapper.find('.cinema-hero__panel-link').exists()).toBe(true)
  })

  it('does not flash the empty note while the showtimes fetch is pending', async () => {
    mockShowtimes(null, true)
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('.cinema-hero__times-empty').exists()).toBe(false)
    expect(wrapper.findAll('.cinema-hero__time')).toHaveLength(0)
  })

  it('labels the panel as real upcoming screenings, not sample data', async () => {
    mockShowtimes([makeShowtime('st-1', '2026-06-10T23:30:00Z')])
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    const panel = wrapper.find('.cinema-hero__panel')
    expect(panel.attributes('aria-label')).toBe('Upcoming screening times')
    expect(panel.text()).not.toContain('Typical Programme')
  })
})
