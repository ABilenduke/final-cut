// Featured slide returned by GET /api/featured-slides.
// Only currently-active, in-window slides, ordered by display_order.
export interface FeaturedSlide {
  id: string | number
  headline: string
  subHeadline: string | null
  imageUrl: string | null
  ctaLabel: string | null
  ctaHref: string | null
}

export interface FeaturedSlidesResponse {
  data: FeaturedSlide[]
}
