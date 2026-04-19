import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieCastList from '~/components/movie/MovieCastList.vue'
import type { CastMember } from '~/types/movie'

function makeCast(overrides: Partial<CastMember>[] = []): CastMember[] {
  const defaults: CastMember[] = [
    { id: 1, name: 'Actor One', character: 'Hero', profileUrl: 'https://example.com/actor1.jpg' },
    { id: 2, name: 'Actor Two', character: 'Villain', profileUrl: 'https://example.com/actor2.jpg' },
  ]
  if (overrides.length > 0) {
    return overrides.map((o, i) => ({ ...defaults[0], id: i + 1, ...o }))
  }
  return defaults
}

describe('MovieCastList', () => {
  it('renders cast member names', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    expect(wrapper.text()).toContain('Actor One')
    expect(wrapper.text()).toContain('Actor Two')
  })

  it('renders character names', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    expect(wrapper.text()).toContain('Hero')
    expect(wrapper.text()).toContain('Villain')
  })

  it('renders portrait photos with name alt text', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    const photos = wrapper.findAll('.movie-cast__photo')
    expect(photos).toHaveLength(2)
    expect(photos[0].attributes('src')).toBe('https://example.com/actor1.jpg')
    expect(photos[0].attributes('alt')).toBe('Actor One')
  })

  it('renders nothing when cast is empty', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: [] },
    })
    expect(wrapper.find('.movie-cast').exists()).toBe(false)
  })

  it('photos have lazy loading', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    const photos = wrapper.findAll('.movie-cast__photo')
    for (const photo of photos) {
      expect(photo.attributes('loading')).toBe('lazy')
    }
  })

  it('falls back to a glyph when profileUrl is null', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: {
        cast: [
          { id: 1, name: 'No Photo', character: 'Mystery', profileUrl: null },
        ],
      },
    })
    expect(wrapper.find('.movie-cast__photo').exists()).toBe(false)
    expect(wrapper.find('.movie-cast__glyph').exists()).toBe(true)
    expect(wrapper.find('.movie-cast__glyph').text()).toBe('N')
  })

  it('limits to 6 principal cast members', async () => {
    const many = Array.from({ length: 10 }, (_, i) => ({
      id: i + 1,
      name: `Actor ${i + 1}`,
      character: `Role ${i + 1}`,
      profileUrl: null,
    }))
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: many },
    })
    expect(wrapper.findAll('.movie-cast__card')).toHaveLength(6)
  })

  it('renders the section eyebrow', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    expect(wrapper.find('.bay-eyebrow').text()).toBe('Cast · Principal')
  })
})
