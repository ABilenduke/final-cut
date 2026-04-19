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
    synopsis: 'The first sentence is the lead. The remaining copy becomes body.',
    runtime: 120,
    rating: 7.5,
    releaseDate: '2026-04-03',
    genres: [
      { id: 1, name: 'Drama' },
      { id: 2, name: 'Thriller' },
    ],
    cast: [],
    posterUrl: 'https://example.com/poster.jpg',
    backdropUrl: 'https://example.com/backdrop.jpg',
    trailerKey: 'abc123',
    status: 'now_showing',
    ...overrides,
  }
}

describe('MovieDetail', () => {
  it('splits synopsis into a lead sentence and body paragraphs', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const lead = wrapper.find('.movie-detail__lead')
    expect(lead.exists()).toBe(true)
    expect(lead.text()).toBe('The first sentence is the lead.')

    const paras = wrapper.findAll('.movie-detail__para')
    expect(paras).toHaveLength(1)
    expect(paras[0].text()).toBe('The remaining copy becomes body.')
  })

  it('falls back to a single lead when synopsis has no sentence boundary', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ synopsis: 'One sentence synopsis' }) },
    })
    const lead = wrapper.find('.movie-detail__lead')
    expect(lead.exists()).toBe(true)
    expect(lead.text()).toBe('One sentence synopsis')
    expect(wrapper.findAll('.movie-detail__para')).toHaveLength(0)
  })

  it('renders synopsis eyebrow and credits eyebrow', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const eyebrows = wrapper.findAll('.bay-eyebrow').map(e => e.text())
    expect(eyebrows).toContain('Synopsis · Reel 01')
    expect(eyebrows).toContain('Credits · Programme Notes')
  })

  it('renders runtime in the credits table', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ runtime: 120 }) },
    })
    const rows = wrapper.findAll('.movie-detail__row')
    const runtimeRow = rows.find(r => r.find('.movie-detail__k').text() === 'Runtime')
    expect(runtimeRow).toBeDefined()
    expect(runtimeRow!.find('.movie-detail__v').text()).toBe('2h 0m')
  })

  it('renders joined genre list in the credits table', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const rows = wrapper.findAll('.movie-detail__row')
    const genresRow = rows.find(r => r.find('.movie-detail__k').text() === 'Genres')
    expect(genresRow).toBeDefined()
    expect(genresRow!.find('.movie-detail__v').text()).toBe('Drama, Thriller')
  })

  it('shows em-dash when there are no genres', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie({ genres: [] }) },
    })
    const rows = wrapper.findAll('.movie-detail__row')
    const genresRow = rows.find(r => r.find('.movie-detail__k').text() === 'Genres')
    expect(genresRow!.find('.movie-detail__v').text()).toBe('—')
  })

  it('includes stubbed crew fact rows', async () => {
    const wrapper = await mountSuspended(MovieDetail, {
      props: { movie: makeMovie() },
    })
    const keys = wrapper.findAll('.movie-detail__k').map(k => k.text())
    // Core credit keys from the design
    expect(keys).toEqual(
      expect.arrayContaining([
        'Director',
        'Screenplay',
        'Cinematography',
        'Editor',
        'Composer',
        'Runtime',
        'Genres',
        'Aspect',
        'Advisory',
      ]),
    )
  })
})
