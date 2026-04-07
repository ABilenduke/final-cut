import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieHero from '~/components/movie/MovieHero.vue'
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

describe('MovieHero', () => {
  it('renders movie title', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.text()).toContain('Blade Runner 2049')
  })

  it('renders tagline', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.text()).toContain('The key to the future is finally unearthed.')
  })

  it('hides tagline when empty', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ tagline: '' }) },
    })
    expect(wrapper.find('.movie-hero__tagline').exists()).toBe(false)
  })

  it('backdrop image is decorative', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('aria-hidden')).toBe('true')
    expect(img.attributes('alt')).toBe('')
  })

  it('does not render backdrop image when backdropUrl is empty', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ backdropUrl: '' }) },
    })
    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('uses wide-frame class', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const section = wrapper.find('section')
    expect(section.classes()).toContain('wide-frame')
  })

  it('has no interactive elements', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('a').exists()).toBe(false)
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('[role="button"]').exists()).toBe(false)
  })
})
