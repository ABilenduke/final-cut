import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieTrailerEmbed from '~/components/movie/MovieTrailerEmbed.vue'

describe('MovieTrailerEmbed', () => {
  it('does not render the iframe until play is clicked', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    expect(wrapper.find('iframe').exists()).toBe(false)
  })

  it('renders the iframe with the real trailer key after clicking play', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    await wrapper.find('.movie-trailer__play').trigger('click')
    const iframe = wrapper.find('iframe')
    expect(iframe.exists()).toBe(true)
    expect(iframe.attributes('src')).toContain('https://www.youtube.com/embed/abc123')
    expect(iframe.attributes('loading')).toBe('lazy')
    expect(iframe.attributes('title')).toBe('Test Movie trailer')
  })

  it('disables the play button and shows fallback caption when no trailer key', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: null, title: 'Test Movie' },
    })
    const play = wrapper.find('.movie-trailer__play')
    expect(play.exists()).toBe(true)
    expect(play.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Trailer unavailable')
  })

  it('renders the clips sidebar with at least 5 entries', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    const clips = wrapper.findAll('.movie-trailer__clip')
    expect(clips.length).toBeGreaterThanOrEqual(5)
  })

  it('swaps the active clip on click', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Test Movie' },
    })
    const clips = wrapper.findAll('.movie-trailer__clip')
    // First clip (the real trailer) is active by default
    expect(clips[0].classes()).toContain('movie-trailer__clip--active')
    await clips[1].trigger('click')
    expect(clips[1].classes()).toContain('movie-trailer__clip--active')
    expect(clips[0].classes()).not.toContain('movie-trailer__clip--active')
  })

  it('renders the section title with the movie name italicized', async () => {
    const wrapper = await mountSuspended(MovieTrailerEmbed, {
      props: { trailerKey: 'abc123', title: 'Dune' },
    })
    expect(wrapper.find('.bay-title').text()).toContain('first look')
    expect(wrapper.text()).toContain('Dune')
  })
})
