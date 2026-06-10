// app/types/faq.ts

/** One FAQ category group from GET /api/faq — the static-era contract. */
export interface FaqCategory {
  category: string
  items: Array<{ question: string; answer: string }>
}

export interface FaqResponse {
  data: FaqCategory[]
}
