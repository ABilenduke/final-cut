import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeNowShowingReel from '~/components/home/HomeNowShowingReel.vue'
import type { Movie } from '~/types/movie'

function movie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'a-film',
    title: 'A Film',
    tagline: '',
    synopsis: '',
    runtime: 120,
    rating: 7.5,
    // Old release so the computed tag is never the time-based "New".
    releaseDate: '2019-01-01',
    genres: [{ id: 1, name: 'Drama' }],
    cast: [],
    posterUrl: 'https://example.com/p.jpg',
    backdropUrl: '',
    trailerKey: null,
    status: 'now_showing',
    ...overrides,
  }
}

describe('HomeNowShowingReel — G9 admin tag override', () => {
  it('renders the admin-set homeTeaserTag (styled gold) instead of the computed tag', async () => {
    const wrapper = await mountSuspended(HomeNowShowingReel, {
      props: { movies: [movie({ homeTeaserTag: 'Final Week' })] },
    })

    const tag = wrapper.find('.now-showing__tag')
    expect(tag.text()).toBe('Final Week')
    expect(tag.classes()).toContain('now-showing__tag--gold')
  })

  it('falls back to the computed tag when no override is set', async () => {
    const wrapper = await mountSuspended(HomeNowShowingReel, {
      props: { movies: [movie({ homeTeaserTag: null })] },
    })

    const tag = wrapper.find('.now-showing__tag')
    // Index 0, non-recent → computed "70mm"; never the admin override.
    expect(tag.text()).not.toBe('Final Week')
    expect(['70mm', 'IMAX', 'Select', 'New']).toContain(tag.text())
  })

  it('treats a blank override as no override', async () => {
    const wrapper = await mountSuspended(HomeNowShowingReel, {
      props: { movies: [movie({ homeTeaserTag: '   ' })] },
    })

    expect(wrapper.find('.now-showing__tag').text()).not.toBe('   ')
  })
})
