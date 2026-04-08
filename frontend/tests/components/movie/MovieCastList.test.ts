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

  it('renders avatar photos', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    const photos = wrapper.findAll('.cast-list__photo')
    expect(photos).toHaveLength(2)
    expect(photos[0].attributes('src')).toBe('https://example.com/actor1.jpg')
    expect(photos[0].attributes('alt')).toBe('Actor One')
  })

  it('hidden when cast is empty', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: [] },
    })
    expect(wrapper.find('.cast-list').exists()).toBe(false)
  })

  it('uses circular avatar class', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    const avatar = wrapper.find('.cast-list__avatar')
    expect(avatar.exists()).toBe(true)
  })

  it('photos have lazy loading', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: { cast: makeCast() },
    })
    const photos = wrapper.findAll('.cast-list__photo')
    for (const photo of photos) {
      expect(photo.attributes('loading')).toBe('lazy')
    }
  })

  it('does not render photo when profileUrl is null', async () => {
    const wrapper = await mountSuspended(MovieCastList, {
      props: {
        cast: [
          { id: 1, name: 'No Photo', character: 'Mystery', profileUrl: null },
        ],
      },
    })
    expect(wrapper.find('.cast-list__photo').exists()).toBe(false)
    // Avatar placeholder container should still exist
    expect(wrapper.find('.cast-list__avatar').exists()).toBe(true)
  })
})
