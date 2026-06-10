// app/types/blog-post.ts

/**
 * Admin-authored blog post from GET /api/blog-posts. The field names match
 * the static-data-era contract (`date` is the published ISO date string).
 * `body` is plain text — blank lines delimit paragraphs — and is only
 * present on the detail endpoint.
 */
export interface BlogPost {
  id: string
  title: string
  slug: string
  excerpt: string
  date: string
  author: string
  imageUrl?: string | null
  body?: string
}

export interface BlogPostsResponse {
  data: BlogPost[]
}

export interface BlogPostResponse {
  data: BlogPost
}
