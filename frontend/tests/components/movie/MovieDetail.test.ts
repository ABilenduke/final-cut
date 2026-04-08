import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieDetail from '~/components/movie/MovieDetail.vue'
import type { Movie } from '~/types/movie'

function makeMovie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'test-movie',
    title: 'Test Movie',
    tagline: 'A test tagline',
    synopsis: 'A test synopsis',
    runtime: 120,
    rating: 7.5,
    releaseDate: '2026-04-03',
    genres: [
      { id: 1, name: 'Drama' },
      { id: 2, name: 'Thriller' },
    ],
    cast: [
      { id: 1, name: 'Actor One', character: 'Hero', profileUrl: 'https://example.com/actor.jpg' },
    ],
    posterUrl: 'https://example.com/poster.jpg',
    backdropUrl: 'https://example.com/backdrop.jpg',
    trailerKey: 'abc123',
    status: 'now_showing',
    ...overrides,
  }
}

describe('MovieDetail', () => {
  it('renders title', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const title = wrapper.find('.movie-detail__title')
    expect(title.exists()).toBe(true)
    expect(title.text()).toBe('Test Movie')
  })

  it('renders tagline', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const tagline = wrapper.find('.movie-detail__tagline')
    expect(tagline.exists()).toBe(true)
    expect(tagline.text()).toBe('A test tagline')
  })

  it('hides tagline when empty', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ tagline: '' }) },
    })
    expect(wrapper.find('.movie-detail__tagline').exists()).toBe(false)
  })

  it('renders synopsis', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const synopsis = wrapper.find('.movie-detail__synopsis')
    expect(synopsis.exists()).toBe(true)
    expect(synopsis.text()).toBe('A test synopsis')
  })

  it('renders runtime via formatRuntime', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ runtime: 120 }) },
    })
    const runtime = wrapper.find('.movie-detail__runtime')
    expect(runtime.exists()).toBe(true)
    expect(runtime.text()).toBe('2h 0m')
  })

  it('renders genre badges', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const genres = wrapper.find('.movie-detail__genres')
    expect(genres.exists()).toBe(true)
    expect(genres.text()).toContain('Drama')
    expect(genres.text()).toContain('Thriller')
  })

  it('hides genre section when no genres', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ genres: [] }) },
    })
    expect(wrapper.find('.movie-detail__genres').exists()).toBe(false)
  })

  it('includes MovieTrailerEmbed when trailerKey exists', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ trailerKey: 'abc123' }) },
    })
    const trailer = wrapper.find('.trailer-embed')
    expect(trailer.exists()).toBe(true)
  })

  it('excludes MovieTrailerEmbed when trailerKey is null', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ trailerKey: null }) },
    })
    expect(wrapper.find('.trailer-embed').exists()).toBe(false)
  })

  it('includes MovieCastList', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const castList = wrapper.find('.cast-list')
    expect(castList.exists()).toBe(true)
  })

  it('renders MovieRatingBadge', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ rating: 8.5 }) },
    })
    expect(wrapper.text()).toContain('8.5')
  })
})
