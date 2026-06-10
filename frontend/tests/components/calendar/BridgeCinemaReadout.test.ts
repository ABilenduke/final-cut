import { describe, it, expect, beforeEach, vi } from 'vitest'
import { ref } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { useApiFetch } from '~/utils/api'
import BridgeCinemaReadout from '~/components/calendar/BridgeCinemaReadout.vue'

const mockUseApiFetch = vi.mocked(useApiFetch)

function mockReadout(data: Record<string, unknown> | null) {
  mockUseApiFetch.mockImplementation(((path: string) => {
    if (path === '/api/cinema-readout') {
      return {
        data: ref(data === null ? null : { data }),
        pending: ref(false),
        error: ref(null),
        refresh: vi.fn(),
      }
    }
    throw new Error(`Unexpected fetch: ${path}`)
  }) as any)
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('BridgeCinemaReadout', () => {
  it('renders live stats from the readout API', async () => {
    mockReadout({
      screeningsToday: 7,
      doorsOpen: '11:00',
      lateShowing: { time: '22:30', auditorium: 'Screen 3' },
      seatsLeftTonight: 412,
    })
    const wrapper = await mountSuspended(BridgeCinemaReadout)

    expect(wrapper.text()).toContain('Screenings today')
    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('22:30 · Screen 3')
    expect(wrapper.text()).toContain('412')
    // The static stub copy must not leak alongside live data.
    expect(wrapper.text()).not.toContain('Valet')
  })

  it('omits null-valued stats instead of rendering blanks', async () => {
    mockReadout({
      screeningsToday: 0,
      doorsOpen: null,
      lateShowing: null,
      seatsLeftTonight: null,
    })
    const wrapper = await mountSuspended(BridgeCinemaReadout)

    expect(wrapper.text()).toContain('Screenings today')
    expect(wrapper.text()).not.toContain('Late showing')
    expect(wrapper.text()).not.toContain('Doors open')
  })

  it('falls back to the static stub when the API is unreachable', async () => {
    mockReadout(null)
    const wrapper = await mountSuspended(BridgeCinemaReadout)

    expect(wrapper.text()).toContain('Members tonight')
    expect(wrapper.text()).toContain('Valet')
  })
})
