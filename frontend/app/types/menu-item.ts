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
}

export type Allergen = 'nuts' | 'dairy' | 'gluten' | 'soy' | 'eggs' | 'shellfish'
export type DietaryTag = 'vegan' | 'vegetarian' | 'gluten_free'
