import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'

// useRuntimeConfig/useRoute/useHead are Nuxt auto-imports resolved at module
// load, so mockNuxtImport (not vi.stubGlobal) is the way to intercept them.
// Routing useHead through a spy lets us invoke the reactive getter useSeo
// registers and assert the head it would produce — deterministic, no DOM head
// introspection needed (the heavy lifting is covered by tests/utils/seo.test.ts).
//
// vi.hoisted is required: mockNuxtImport factories are hoisted above the file
// body, and Nuxt's route-announcer calls useHead at import time, so a plain
// `const headSpy` would hit a temporal-dead-zone error. Hoisting the holders
// guarantees they exist before any mocked import runs.
const { headSpy, config } = vi.hoisted(() => ({
  headSpy: vi.fn(),
  config: { siteUrl: 'https://finalcut.test' },
}))

mockNuxtImport('useRuntimeConfig', () => {
  return () => ({ public: { siteUrl: config.siteUrl } }) as ReturnType<typeof useRuntimeConfig>
})
mockNuxtImport('useRoute', () => {
  return () => ({ path: '/current' }) as ReturnType<typeof useRoute>
})
mockNuxtImport('useHead', () => headSpy)

import { useSeo } from '~/composables/useSeo'

interface ResolvedHead {
  title: string
  meta: Array<Record<string, string>>
  link: Array<Record<string, string>>
}

function lastHead(): ResolvedHead {
  const calls = headSpy.mock.calls
  const getter = calls[calls.length - 1]![0] as () => ResolvedHead
  return getter()
}

describe('useSeo', () => {
  beforeEach(() => {
    headSpy.mockClear()
    config.siteUrl = 'https://finalcut.test'
  })

  it('registers a reactive head built from the input', () => {
    useSeo(() => ({ title: 'Events', description: 'All the events.' }))

    expect(headSpy).toHaveBeenCalledTimes(1)
    const head = lastHead()
    expect(head.title).toBe('Events')
    expect(head.link).toContainEqual({ rel: 'canonical', href: 'https://finalcut.test/current' })
    expect(head.meta).toContainEqual({ property: 'og:image', content: 'https://finalcut.test/og-default.png' })
  })

  it('uses an explicit path over the current route', () => {
    useSeo(() => ({ title: 'Gala', description: 'd', path: '/events/gala' }))
    expect(lastHead().link).toContainEqual({
      rel: 'canonical',
      href: 'https://finalcut.test/events/gala',
    })
  })

  it('re-evaluates reactively (getter is called each time the head resolves)', () => {
    let title = 'Loading'
    useSeo(() => ({ title, description: 'd' }))

    expect(lastHead().title).toBe('Loading')
    title = 'Loaded'
    expect(lastHead().title).toBe('Loaded')
  })
})
