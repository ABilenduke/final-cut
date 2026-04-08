import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BlogIndex from '~/pages/blog/index.vue'

// Note: queryCollection is auto-imported from @nuxt/content and cannot be
// easily mocked in the Nuxt test environment. The blog listing page gracefully
// handles null/empty data. BlogPostCard rendering is covered by its own tests.

describe('Blog Listing Page', () => {
  it('renders page title', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    expect(wrapper.find('.blog-page__title').text()).toBe('Blog')
  })

  it('shows empty state when no content is available', async () => {
    const wrapper = await mountSuspended(BlogIndex)
    // Without mocked content data, the page renders the empty state
    expect(wrapper.find('.blog-page__empty').exists()).toBe(true)
    expect(wrapper.text()).toContain('No posts yet')
  })
})
