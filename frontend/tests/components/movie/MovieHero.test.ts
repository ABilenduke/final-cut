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
  it('renders the film title', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    expect(wrapper.text()).toContain('Blade Runner')
    expect(wrapper.text()).toContain('2049')
  })

  it('renders the tagline', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const tagline = wrapper.find('.movie-hero__tagline')
    expect(tagline.exists()).toBe(true)
    expect(tagline.text()).toBe('The key to the future is finally unearthed.')
  })

  it('hides tagline when empty', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ tagline: '' }) },
    })
    expect(wrapper.find('.movie-hero__tagline').exists()).toBe(false)
  })

  it('renders the backdrop image with empty alt and aria-hidden', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const backdrop = wrapper.find('.movie-hero__backdrop-img')
    expect(backdrop.exists()).toBe(true)
    expect(backdrop.attributes('aria-hidden')).toBe('true')
    expect(backdrop.attributes('alt')).toBe('')
  })

  it('does not render backdrop image when backdropUrl is empty', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ backdropUrl: '' }) },
    })
    expect(wrapper.find('.movie-hero__backdrop-img').exists()).toBe(false)
  })

  it('renders poster image with descriptive alt text', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const poster = wrapper.find('.movie-hero__poster-img')
    expect(poster.exists()).toBe(true)
    expect(poster.attributes('alt')).toBe('Blade Runner 2049 poster')
  })

  it('falls back to a glyph when posterUrl is absent', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ posterUrl: '' }) },
    })
    expect(wrapper.find('.movie-hero__poster-img').exists()).toBe(false)
    expect(wrapper.find('.movie-hero__poster-glyph').exists()).toBe(true)
  })

  it('renders the Get Tickets CTA', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const buttons = wrapper.findAll('button')
    expect(buttons.some(b => b.text().includes('Get Tickets'))).toBe(true)
  })

  it('renders Watch Trailer CTA only when trailerKey exists', async () => {
    const withTrailer = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ trailerKey: 'abc' }) },
    })
    expect(withTrailer.text()).toContain('Watch Trailer')

    const withoutTrailer = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ trailerKey: null }) },
    })
    expect(withoutTrailer.text()).not.toContain('Watch Trailer')
  })

  it('renders crew stat labels', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const text = wrapper.text()
    expect(text).toContain('Director')
    expect(text).toContain('Writer')
    expect(text).toContain('Cinematographer')
    expect(text).toContain('Composer')
    expect(text).toContain('Studio')
  })

  it('status shows "Now Showing" for now_showing movies', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ status: 'now_showing' }) },
    })
    const meta = wrapper.find('.movie-hero__t-meta')
    expect(meta.text()).toContain('Now Showing')
  })

  it('status shows "Coming Soon" for coming_soon movies', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie({ status: 'coming_soon' }) },
    })
    const meta = wrapper.find('.movie-hero__t-meta')
    expect(meta.text()).toContain('Coming Soon')
  })

  it('clock placeholder is shown during SSR / first paint', async () => {
    const wrapper = await mountSuspended(MovieHero, {
      props: { movie: makeMovie() },
    })
    const clock = wrapper.find('.movie-hero__clock')
    expect(clock.exists()).toBe(true)
    // During the test harness the client tick may have fired already; accept either placeholder or HH:MM:SS
    expect(clock.text()).toMatch(/^(--:--:--|\d{2}:\d{2}:\d{2})$/)
  })
})
