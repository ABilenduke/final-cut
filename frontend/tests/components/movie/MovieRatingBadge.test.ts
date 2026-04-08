import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MovieRatingBadge from '~/components/movie/MovieRatingBadge.vue'

describe('MovieRatingBadge', () => {
  it('renders rating to one decimal', async () => {
    const wrapper = await mountSuspended(MovieRatingBadge, {
      props: { rating: 7 },
    })
    expect(wrapper.text()).toBe('7.0')
  })

  it('renders decimal ratings correctly', async () => {
    const wrapper = await mountSuspended(MovieRatingBadge, {
      props: { rating: 8.46 },
    })
    expect(wrapper.text()).toBe('8.5')
  })

  it('handles zero', async () => {
    const wrapper = await mountSuspended(MovieRatingBadge, {
      props: { rating: 0 },
    })
    expect(wrapper.text()).toBe('0.0')
  })

  it('handles max rating', async () => {
    const wrapper = await mountSuspended(MovieRatingBadge, {
      props: { rating: 10 },
    })
    expect(wrapper.text()).toBe('10.0')
  })

  it('passes accent variant to CvBadge', async () => {
    const wrapper = await mountSuspended(MovieRatingBadge, {
      props: { rating: 7.8 },
    })
    const html = wrapper.html()
    expect(html).toContain('cv-badge--accent')
  })
})
