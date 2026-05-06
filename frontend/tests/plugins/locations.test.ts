import { describe, it, expect, vi } from 'vitest'

/**
 * Plan 13 Task 2: the locations plugin is now intentionally empty.
 *
 * The old plugin called `initializeLocations` on `app:mounted` to rehydrate
 * `activeLocation` from localStorage at boot. That boot-time coupling was
 * removed: `activeLocation` is now lazy (null until the booking flow or an
 * explicit user action sets it). The plugin no longer needs to do anything at
 * app startup.
 */
describe('locations plugin', () => {
  it('is a no-op — does not call hook or touch location state', async () => {
    const plugin = (await import('~/plugins/locations')).default
    const hook = vi.fn()

    await plugin({ hook } as never)

    // The intentionally-empty plugin must not register any hooks.
    expect(hook).not.toHaveBeenCalled()
  })
})
