import type { BlogPostResponse, BlogPostsResponse } from '~/types/blog-post'
import { useApiFetch } from '~/utils/api'

/**
 * SSR-friendly fetch wrappers for the admin-authored blog
 * (admin-v2 Plan 12 — replaced the static ~/data/blog.ts).
 *
 * The list endpoint returns published posts newest-first without bodies;
 * the detail endpoint includes the body and 404s drafts.
 */
export function useBlogPosts() {
  return useApiFetch<BlogPostsResponse>('/api/blog-posts', {
    key: 'blog-posts',
  })
}

export function useBlogPost(slug: string) {
  return useApiFetch<BlogPostResponse>(`/api/blog-posts/${slug}`, {
    key: `blog-post-${slug}`,
  })
}
