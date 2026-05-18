import { describe, it, expect } from 'vitest'
import { menuData } from '~/data/menu'

describe('menuData (static editorial fallback)', () => {
  it('does not contain any /images/menu/ legacy paths', () => {
    // The legacy /images/menu/* paths never resolved (frontend/public has no
    // images/ directory). The new convention is bare CDN-relative paths like
    // "concessions/popcorn_sm.webp" — the assetUrl helper prefixes them with
    // NUXT_PUBLIC_CDN_BASE_URL. Items without a matching CDN webp are set to
    // an empty string so the gradient fallback in ConcessionItemCard kicks in.
    for (const item of menuData) {
      expect(item.imageUrl).not.toContain('/images/menu/')
    }
  })

  it('points every populated imageUrl at the concessions/ CDN path', () => {
    for (const item of menuData) {
      if (!item.imageUrl) {
        continue
      }
      expect(item.imageUrl).toMatch(/^concessions\/.+\.webp$/)
    }
  })
})
