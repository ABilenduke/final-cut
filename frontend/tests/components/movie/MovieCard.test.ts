import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieCard from '~/components/movie/MovieCard.vue'
import type { Movie } from '~/types/movie'

function makeMovie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'test-movie',
    title: 'Test Movie',
    tagline: 'A test tagline.',
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
  it('renders the title', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ title: 'Interstellar' }) },
    })
    expect(wrapper.text()).toContain('Interstellar')
  })

  it('renders the rating chip with one decimal when showShowtimes is true', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ rating: 8.2 }) },
    })
    const rating = wrapper.find('.movie-card__rating')
    expect(rating.exists()).toBe(true)
    expect(rating.text()).toContain('8.2')
  })

  it('omits the rating chip when showShowtimes is false (coming soon)', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ rating: 8.2 }), showShowtimes: false },
    })
    expect(wrapper.find('.movie-card__rating').exists()).toBe(false)
  })

  it('omits the rating chip when rating is null', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ rating: null }) },
    })
    expect(wrapper.find('.movie-card__rating').exists()).toBe(false)
  })

  it('renders the "Now playing" tag in showtimes mode', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    const tag = wrapper.find('.movie-card__tag')
    expect(tag.exists()).toBe(true)
    expect(tag.text()).toBe('Now playing')
    expect(tag.classes()).not.toContain('movie-card__tag--soon')
  })

  it('renders the "Coming soon" gold tag when showShowtimes is false', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie(), showShowtimes: false },
    })
    const tag = wrapper.find('.movie-card__tag')
    expect(tag.text()).toBe('Coming soon')
    expect(tag.classes()).toContain('movie-card__tag--soon')
  })

  it('renders the release date in the footer when showShowtimes is false', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: {
        movie: makeMovie({ releaseDate: '2026-06-15' }),
        showShowtimes: false,
      },
    })
    const release = wrapper.find('.movie-card__release')
    expect(release.exists()).toBe(true)
    expect(release.text()).toContain('Releases')
    expect(release.text()).toContain('Jun 15, 2026')
  })

  it('renders the muted "Showtimes nightly" label in showtimes mode', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    const release = wrapper.find('.movie-card__release')
    expect(release.classes()).toContain('movie-card__release--muted')
    expect(release.text()).toContain('Showtimes nightly')
  })

  it('renders the runtime as Xh Ym in the meta row', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ runtime: 142 }) },
    })
    expect(wrapper.find('.movie-card__diet--time').text()).toBe('2h 22m')
  })

  it('handles a runtime that is exactly hours (no minutes)', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ runtime: 120 }) },
    })
    expect(wrapper.find('.movie-card__diet--time').text()).toBe('2h')
  })

  it('handles a sub-hour runtime', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ runtime: 45 }) },
    })
    expect(wrapper.find('.movie-card__diet--time').text()).toBe('45m')
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
    // 4 chips total: 1 runtime + 3 genres
    const meta = wrapper.find('.movie-card__meta')
    const chips = meta.findAll('.movie-card__diet')
    expect(chips).toHaveLength(4)
    const text = meta.text()
    expect(text).toContain('Action')
    expect(text).toContain('Drama')
    expect(text).toContain('Thriller')
    expect(text).not.toContain('Horror')
    expect(text).not.toContain('Comedy')
  })

  it('renders no genre badges when genres is empty (only runtime chip remains)', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ genres: [] }) },
    })
    const chips = wrapper.find('.movie-card__meta').findAll('.movie-card__diet')
    expect(chips).toHaveLength(1)
    expect(chips[0].classes()).toContain('movie-card__diet--time')
  })

  it('shows the index marker as zero-padded "Reel · NN" when index is provided', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie(), index: 3 },
    })
    const idx = wrapper.find('.movie-card__idx')
    expect(idx.exists()).toBe(true)
    expect(idx.text()).toBe('Reel · 03')
  })

  it('omits the index marker when index is not provided', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('.movie-card__idx').exists()).toBe(false)
  })

  it('renders the "Showtimes" CTA in showtimes mode', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    const cta = wrapper.find('.movie-card__cta')
    expect(cta.exists()).toBe(true)
    expect(cta.text()).toContain('Showtimes')
    // Renders as a link to the detail page
    expect(cta.attributes('href')).toBe('/movies/test-movie')
  })

  it('renders a Notify Me button in coming-soon mode and emits notify with slug on click', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ slug: 'inception' }), showShowtimes: false },
    })
    const cta = wrapper.find('.movie-card__cta')
    expect(cta.element.tagName).toBe('BUTTON')
    expect(cta.text()).toContain('Notify me')

    await cta.trigger('click')
    expect(wrapper.emitted('notify')).toEqual([['inception']])
  })

  it('falls back to the gradient + glyph when posterUrl is empty', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ title: 'Endurance', posterUrl: '' }) },
    })
    expect(wrapper.find('.movie-card__poster').exists()).toBe(false)
    const glyph = wrapper.find('.movie-card__glyph')
    expect(glyph.exists()).toBe(true)
    expect(glyph.text()).toBe('E')
  })

  it('renders the poster image when posterUrl is set', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie({ posterUrl: 'https://example.com/p.jpg' }) },
    })
    const img = wrapper.find('.movie-card__poster')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/p.jpg')
    expect(img.attributes('alt')).toBe('Test Movie poster')
  })

  it('defaults to showShowtimes true', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('.movie-card__rating').exists()).toBe(true)
    expect(wrapper.find('.movie-card__cta').text()).toContain('Showtimes')
  })

  it('applies the movie-card class to the root', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.find('.movie-card').exists()).toBe(true)
  })

  it('applies the movie-card--coming class on the root in coming-soon mode', async () => {
    const wrapper = await mountSuspended(MovieCard, {
      props: { movie: makeMovie(), showShowtimes: false },
    })
    expect(wrapper.find('.movie-card').classes()).toContain('movie-card--coming')
  })
})
