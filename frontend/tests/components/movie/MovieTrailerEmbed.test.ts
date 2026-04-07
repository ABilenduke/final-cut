import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieTrailerEmbed from '~/components/movie/MovieTrailerEmbed.vue'

describe('MovieTrailerEmbed', () => {
  it('renders iframe with correct src', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    const iframe = wrapper.find('iframe')
    expect(iframe.exists()).toBe(true)
    expect(iframe.attributes('src')).toBe('https://www.youtube.com/embed/abc123')
  })

  it('iframe has accessible title', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    const iframe = wrapper.find('iframe')
    expect(iframe.attributes('title')).toBe('Test Movie trailer')
  })

  it('iframe has loading="lazy"', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    const iframe = wrapper.find('iframe')
    expect(iframe.attributes('loading')).toBe('lazy')
  })

  it('container has aspect-video class', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    const container = wrapper.find('.trailer-embed__container')
    expect(container.exists()).toBe(true)
    expect(container.classes()).toContain('aspect-video')
  })
})
