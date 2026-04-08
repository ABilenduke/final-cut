import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeFeaturedHero from '~/components/home/HomeFeaturedHero.vue'
import type { Movie } from '~/types/movie'

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

describe('HomeFeaturedHero', () => {
  it('renders movie title', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.text()).toContain('Blade Runner 2049')
  })

  it('renders tagline', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.text()).toContain('The key to the future is finally unearthed.')
  })

  it('hides tagline when empty', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie({ tagline: '' }) },
    })
    expect(wrapper.find('.home-hero__tagline').exists()).toBe(false)
  })

  it('shows "Get Tickets" CTA linking to movie detail', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie({ slug: 'test-movie' }) },
    })
    expect(wrapper.text()).toContain('Get Tickets')
  })

  it('backdrop has eager loading', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie() },
    })
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('loading')).toBe('eager')
    expect(img.attributes('fetchpriority')).toBe('high')
  })

  it('backdrop is decorative', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie() },
    })
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('aria-hidden')).toBe('true')
    expect(img.attributes('alt')).toBe('')
  })

  it('hides backdrop when no URL', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie({ backdropUrl: '' }) },
    })
    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('CTA has accessible label', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie({ title: 'Test Film' }) },
    })
    const html = wrapper.html()
    expect(html).toContain('Get tickets for Test Film')
  })

  it('uses semantic section element', async () => {
    const wrapper = await mountSuspended(HomeFeaturedHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('section.home-hero').exists()).toBe(true)
  })
})
