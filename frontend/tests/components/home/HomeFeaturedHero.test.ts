import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeFeaturedHero from '~/components/home/HomeFeaturedHero.vue'
import type { Movie } from '~/types/movie'
import type { Showtime } from '~/types/showtime'

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

function makeShowtime(overrides: Partial<Showtime> = {}): Showtime {
  return {
    id: 'st-1',
    movieId: 1,
    movieSlug: 'blade-runner-2049',
    movieTitle: 'Blade Runner 2049',
    screenId: 's1',
    screenName: 'Screen 1',
    startTime: '2026-04-07T19:00:00Z',
    endTime: '2026-04-07T21:00:00Z',
    priceStandard: 1500,
    pricePremium: 2000,
    priceAccessible: 1200,
    ...overrides,
  }
}

describe('HomeFeaturedHero', () => {
  it('renders movie title', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: null,
        locationSelected: true,
        loading: false,
      },
    })
    expect(wrapper.text()).toContain('Blade Runner 2049')
  })

  it('renders tagline', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: null,
        locationSelected: true,
        loading: false,
      },
    })
    expect(wrapper.text()).toContain('The key to the future is finally unearthed.')
  })

  it('shows "Get Tickets" with time when showtime provided', async () => {
    const showtime = makeShowtime()
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: showtime,
        locationSelected: true,
        loading: false,
      },
    })
    const text = wrapper.text()
    expect(text).toContain('Get Tickets')
    // The formatted time from Intl.DateTimeFormat
    const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' })
    const expectedTime = timeFormatter.format(new Date(showtime.startTime))
    expect(text).toContain(expectedTime)
  })

  it('CTA links to purchase page', async () => {
    const showtime = makeShowtime({ id: 'st-42' })
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: showtime,
        locationSelected: true,
        loading: false,
      },
    })
    // CvButton with href renders as <cvbutton> stub with href attribute
    const ctaButton = wrapper.find('cvbutton[href="/purchase/st-42"]')
    expect(ctaButton.exists()).toBe(true)
  })

  it('shows "More showtimes" link', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie({ slug: 'test-movie' }),
        nextShowtime: makeShowtime(),
        locationSelected: true,
        loading: false,
      },
    })
    expect(wrapper.text()).toContain('More showtimes')
    // NuxtLink renders as a resolved component in the test environment
    const moreLink = wrapper.find('.home-hero__more-link')
    expect(moreLink.exists()).toBe(true)
  })

  it('shows "View Showtimes" when no showtime', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie({ slug: 'test-movie' }),
        nextShowtime: null,
        locationSelected: true,
        loading: false,
      },
    })
    expect(wrapper.text()).toContain('View Showtimes')
    const fallbackButton = wrapper.find('cvbutton[href="/movies/test-movie"]')
    expect(fallbackButton.exists()).toBe(true)
  })

  it('shows "Choose a Location" when no location selected', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: null,
        locationSelected: false,
        loading: false,
      },
    })
    expect(wrapper.text()).toContain('Choose a Location')
  })

  it('emits choose-location event', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: null,
        locationSelected: false,
        loading: false,
      },
    })
    const button = wrapper.find('cvbutton')
    await button.trigger('click')
    expect(wrapper.emitted('choose-location')).toBeTruthy()
  })

  it('shows skeleton when loading', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: null,
        locationSelected: true,
        loading: true,
      },
    })
    const skeleton = wrapper.find('cvskeletonloader')
    expect(skeleton.exists()).toBe(true)
  })

  it('backdrop has eager loading', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: null,
        locationSelected: true,
        loading: false,
      },
    })
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('loading')).toBe('eager')
    expect(img.attributes('fetchpriority')).toBe('high')
  })

  it('backdrop is decorative', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: {
        movie: makeMovie(),
        nextShowtime: null,
        locationSelected: true,
        loading: false,
      },
    })
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('aria-hidden')).toBe('true')
    expect(img.attributes('alt')).toBe('')
  })
})
