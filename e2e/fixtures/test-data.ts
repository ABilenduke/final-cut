/**
 * Shared test constants matching seeded database data.
 * See backend/database/seeders/ for the source of truth.
 */

// Seeded user accounts (DatabaseSeeder)
export const TEST_USER_EMAIL = 'test@finalcut.test'
export const TEST_USER_PASSWORD = 'password'
export const MEMBER_USER_EMAIL = 'member@finalcut.test'
export const MEMBER_USER_PASSWORD = 'password'

// Stripe test card numbers
export const STRIPE_TEST_CARD = '4242424242424242'
export const STRIPE_3DS_CARD = '4000002500003155'
export const STRIPE_DECLINED_CARD = '4000000000000002'

// Known movie slugs (MovieSeeder — now showing)
export const MOVIE_SLUGS = {
  FIGHT_CLUB: 'fight-club',
  SHAWSHANK: 'the-shawshank-redemption',
  PULP_FICTION: 'pulp-fiction',
  DARK_KNIGHT: 'the-dark-knight',
  MATRIX: 'the-matrix',
  INCEPTION: 'inception',
  INTERSTELLAR: 'interstellar',
  GODFATHER: 'the-godfather',
} as const

// Known coming soon slugs
export const COMING_SOON_SLUGS = {
  JURASSIC_PARK: 'jurassic-park',
  TOY_STORY: 'toy-story',
} as const

// Viewport sizes for responsive tests
export const VIEWPORTS = {
  desktop: { width: 1280, height: 800 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 812 },
} as const

// Maximum seats per transaction
export const MAX_SEATS = 10
