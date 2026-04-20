export interface MenuItem {
  id: string
  name: string
  description: string
  price: number
  category: 'popcorn' | 'drinks' | 'snacks' | 'combos' | 'specials'
  imageUrl: string
  allergens: Allergen[]
  dietary: DietaryTag[]
  available: boolean
  /** Editorial size descriptor — e.g. "Large · 32 oz", "125 ml". */
  size?: string
  /** Curator label shown in the editorial card footer — e.g. "House", "Pairing", "Bar". */
  curator?: string
  /** Optional editorial flag rendered on the thumb — e.g. "Tonight". */
  flag?: string
  /** CSS radial-gradient string for the editorial card thumbnail. Falls back to a category default. */
  gradient?: string
  /** Single-character glyph overlaid on the thumb. Falls back to the first letter of the name. */
  glyph?: string
}

export type Allergen = 'nuts' | 'dairy' | 'gluten' | 'soy' | 'eggs' | 'shellfish'
export type DietaryTag = 'vegan' | 'vegetarian' | 'gluten_free'
