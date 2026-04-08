import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieCard from '~/components/movie/MovieCard.vue'
import type { Movie } from '~/types/movie'

function makeMovie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'test-movie',
    title: 'Test Movie',
    tagline: '',
    synopsis: '',
    runtime: 120,
    rating: 7.5,
    releaseDate: '2026-01-01',
    genres: [
      { id: 1, name: 'Action' },
      { id: 2, name: 'Drama' },
    ],
    cast: [],
    posterUrl: 'https://example.com/poster.jpg',
    backdropUrl: 'https://example.com/backdrop.jpg',
    trailerKey: null,
    status: 'now_showing',
    ...overrides,
  }
}

describe('MovieCard', () => {
  it('renders rating badge when showShowtimes is true (default)', async () => {
    const movie = makeMovie({ rating: 8.2 })
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie },
    })
    expect(wrapper.text()).toContain('8.2')
  })

  it('does not render rating badge when showShowtimes is false', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ rating: 8.2 }), showShowtimes: false },
    })
    // Coming-soon mode should not include MovieRatingBadge
    expect(wrapper.html()).not.toContain('cv-badge--accent')
  })

  it('renders release date when showShowtimes is false', async () => {
    const movie = makeMovie({ releaseDate: '2026-06-15' })
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie, showShowtimes: false },
    })
    expect(wrapper.text()).toContain('Jun 15, 2026')
  })

  it('release date has secondary color class', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie(), showShowtimes: false },
    })
    const releaseDate = wrapper.find('.movie-card__release-date')
    expect(releaseDate.exists()).toBe(true)
    expect(releaseDate.classes()).toContain('label-lg')
  })

  it('does not render release date when showShowtimes is true', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('.movie-card__release-date').exists()).toBe(false)
  })

  it('truncates genres to 3', async () => {
    const movie = makeMovie({
      genres: [
        { id: 1, name: 'Action' },
        { id: 2, name: 'Drama' },
        { id: 3, name: 'Thriller' },
        { id: 4, name: 'Horror' },
        { id: 5, name: 'Comedy' },
      ],
    })
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie },
    })
    const genreContainer = wrapper.find('.movie-card__genres')
    expect(genreContainer.exists()).toBe(true)
    const genreBadges = genreContainer.findAll('.cv-badge')
    expect(genreBadges).toHaveLength(3)
    expect(genreContainer.text()).toContain('Action')
    expect(genreContainer.text()).toContain('Drama')
    expect(genreContainer.text()).toContain('Thriller')
    expect(genreContainer.text()).not.toContain('Horror')
    expect(genreContainer.text()).not.toContain('Comedy')
  })

  it('renders all genres when 3 or fewer', async () => {
    const movie = makeMovie({
      genres: [
        { id: 1, name: 'Action' },
        { id: 2, name: 'Drama' },
      ],
    })
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie },
    })
    const genreContainer = wrapper.find('.movie-card__genres')
    const genreBadges = genreContainer.findAll('.cv-badge')
    expect(genreBadges).toHaveLength(2)
  })

  it('renders no genre badges when genres is empty', async () => {
    const movie = makeMovie({ genres: [] })
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie },
    })
    const genreContainer = wrapper.find('.movie-card__genres')
    const genreBadges = genreContainer.findAll('.cv-badge')
    expect(genreBadges).toHaveLength(0)
  })

  it('applies movie-card class to the root CvCard', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    const card = wrapper.find('.cv-card')
    expect(card.exists()).toBe(true)
    expect(card.classes()).toContain('movie-card')
  })

  it('defaults to showShowtimes true', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.html()).toContain('cv-badge--accent')
    // showShowtimes=true does NOT render release date
    expect(wrapper.find('.movie-card__release-date').exists()).toBe(false)
  })

  it('renders meta section with correct structure', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    const meta = wrapper.find('.movie-card__meta')
    expect(meta.exists()).toBe(true)
    const genres = meta.find('.movie-card__genres')
    expect(genres.exists()).toBe(true)
  })
})
