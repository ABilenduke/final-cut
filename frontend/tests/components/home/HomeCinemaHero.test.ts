import { describe, it, expect, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeCinemaHero from '~/components/home/HomeCinemaHero.vue'
import { placeholderShowtimeSlots, type HeroShowtimeSlot } from '~/data/homepage'
import type { Movie } from '~/types/movie'

const useLocationsSpy = vi.fn(() => {
  throw new Error('useLocations should not be called from HomeCinemaHero')
})
const useShowtimesSpy = vi.fn(() => {
  throw new Error('useShowtimes should not be called from HomeCinemaHero')
})

vi.mock('~/composables/useLocations', () => ({
  useLocations: useLocationsSpy,
}))

vi.mock('~/composables/useShowtimes', () => ({
  useShowtimes: useShowtimesSpy,
}))

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

describe('HomeCinemaHero', () => {
  it('renders the film title', async () => {
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    const title = wrapper.find('.cinema-hero__title')
    expect(title.exists()).toBe(true)
    expect(title.text()).toBe('Blade Runner 2049')
  })

  it('renders the tagline', async () => {
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    const sub = wrapper.find('.cinema-hero__sub')
    expect(sub.exists()).toBe(true)
    expect(sub.text()).toBe('The key to the future is finally unearthed.')
  })

  it('hides tagline when movie has no tagline', async () => {
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie({ tagline: '' }) },
    })
    expect(wrapper.find('.cinema-hero__sub').exists()).toBe(false)
  })

  it('renders all 8 placeholder showtime chips', async () => {
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    const chips = wrapper.findAll('.cinema-hero__time')
    expect(chips).toHaveLength(placeholderShowtimeSlots.length)
    expect(chips).toHaveLength(8)
  })

  it('renders each non-sold-out chip as a link to the movie detail page', async () => {
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie({ slug: 'blade-runner-2049' }) },
    })
    const chipLinks = wrapper.findAll('a.cinema-hero__time')
    const availableSlots = placeholderShowtimeSlots.filter((s: HeroShowtimeSlot) => !s.soldOut)
    expect(chipLinks).toHaveLength(availableSlots.length)
    for (const link of chipLinks) {
      expect(link.attributes('href')).toBe('/movies/blade-runner-2049')
    }
  })

  it('renders sold-out chips as non-link spans', async () => {
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    const soldChips = wrapper.findAll('span.cinema-hero__time--sold')
    const soldSlots = placeholderShowtimeSlots.filter((s: HeroShowtimeSlot) => s.soldOut)
    expect(soldChips).toHaveLength(soldSlots.length)
  })

  it('does not present the panel as live or screen-scoped', async () => {
    const wrapper = await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    const panel = wrapper.find('.cinema-hero__panel')
    expect(panel.exists()).toBe(true)
    expect(panel.attributes('aria-label')).toBe('Sample screening times')

    // No stale "Live" dot / "Tonight · Screen N" copy
    expect(wrapper.find('.cinema-hero__panel-live').exists()).toBe(false)
    expect(wrapper.find('.cinema-hero__panel-live-dot').exists()).toBe(false)
    expect(panel.text()).not.toContain('Live')
    expect(panel.text()).not.toMatch(/Tonight\s*·\s*Screen/)
  })

  it('does not call useLocations or useShowtimes', async () => {
    await mountSuspended(HomeCinemaHero, {
      props: { movie: makeMovie() },
    })
    expect(useLocationsSpy).not.toHaveBeenCalled()
    expect(useShowtimesSpy).not.toHaveBeenCalled()
  })
})
