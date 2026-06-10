import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MoviePress from '~/components/movie/MoviePress.vue'

describe('MoviePress', () => {
  it('renders admin-authored quotes when provided', async () => {
    const wrapper = await mountSuspended(MoviePress, {
      props: {
        quotes: [
          { quote: 'Luminous and exact.', author: 'A. Critic', publication: 'The Times' },
        ],
      },
    })

    expect(wrapper.text()).toContain('Luminous and exact.')
    expect(wrapper.text()).toContain('The Times')
    // The sample copy must not leak alongside real quotes.
    expect(wrapper.text()).not.toContain('Sight & Sound')
  })

  it('falls back to the sample quotes when none are provided', async () => {
    const wrapper = await mountSuspended(MoviePress, { props: { quotes: [] } })

    expect(wrapper.text()).toContain('Sight & Sound')
  })
})
