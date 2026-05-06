// Featured slide returned by GET /api/featured-slides.
// Only currently-active, in-window slides, ordered by display_order.
export interface FeaturedSlide {
  id: string | number
  headline: string
  sub_headline: string | null
  image_url: string | null
  cta_label: string | null
  cta_href: string | null
}

export interface FeaturedSlidesResponse {
  data: FeaturedSlide[]
}
