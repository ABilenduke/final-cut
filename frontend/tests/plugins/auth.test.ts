import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'

const fetchUser = vi.hoisted(() => vi.fn())
const hasAuthSessionMarker = vi.hoisted(() => vi.fn())

mockNuxtImport('useAuth', () => {
  return () => ({ fetchUser })
})

vi.mock('~/composables/useAuth', () => ({
  hasAuthSessionMarker,
  useAuth: () => ({ fetchUser }),
}))

describe('auth plugin', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('does not probe /auth/me during public boot when no prior session marker exists', async () => {
    hasAuthSessionMarker.mockReturnValue(false)
    const plugin = (await import('~/plugins/auth')).default

    await plugin({} as never)

    expect(fetchUser).not.toHaveBeenCalled()
  })

  it('revalidates the session during boot when the browser has a prior auth marker', async () => {
    hasAuthSessionMarker.mockReturnValue(true)
    const plugin = (await import('~/plugins/auth')).default

    await plugin({} as never)

    expect(fetchUser).toHaveBeenCalledTimes(1)
  })
})
