import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockFetchLocations = vi.fn()
const mockInitializeLocations = vi.fn()

vi.mock('~/composables/useLocations', () => ({
  useLocations: () => ({
    fetchLocations: mockFetchLocations,
    initializeLocations: mockInitializeLocations,
  }),
}))

describe('locations plugin', () => {
  beforeEach(() => {
    mockFetchLocations.mockReset()
    mockInitializeLocations.mockReset()
    mockFetchLocations.mockResolvedValue(undefined)
    mockInitializeLocations.mockResolvedValue(undefined)
  })

  it('initializes locations after the app mounts', async () => {
    const plugin = (await import('~/plugins/locations')).default
    const hook = vi.fn(async (_name: string, callback: () => Promise<void>) => {
      await callback()
    })

    await plugin({ hook } as never)

    expect(hook).toHaveBeenCalledWith('app:mounted', expect.any(Function))
    expect(mockInitializeLocations).toHaveBeenCalledTimes(1)
    expect(mockFetchLocations).not.toHaveBeenCalled()
  })
})
