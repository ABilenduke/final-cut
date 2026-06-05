import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, it, expect } from 'vitest'

/**
 * Doc-vs-reality lock. The architecture docs once described `nuxt-auth-utils`
 * as the SSR auth-hydration layer, but it was never adopted. The real mechanism
 * is `useState('auth:user')` + a non-sensitive `localStorage` marker
 * (`fc:auth:session`) that gates the `/api/auth/me` probe, with the Laravel
 * Sanctum cookie as the authoritative session; protected routes are `ssr: false`
 * so there is no server-side auth hydration to perform. This test fails if the
 * unused dependency is ever (re)introduced or an auth module is registered,
 * keeping the docs and code from silently drifting back apart.
 *
 * `__dirname` resolves under the Nuxt Vitest environment.
 */
const ROOT = resolve(__dirname, '../..')

const read = (rel: string): string => readFileSync(resolve(ROOT, rel), 'utf-8')

describe('auth mechanism matches the documented design', () => {
  it('does not depend on nuxt-auth-utils', () => {
    const pkg = JSON.parse(read('package.json')) as {
      dependencies?: Record<string, string>
      devDependencies?: Record<string, string>
    }
    const allDeps = { ...(pkg.dependencies ?? {}), ...(pkg.devDependencies ?? {}) }
    expect(allDeps).not.toHaveProperty('nuxt-auth-utils')
  })

  it('registers no auth module in nuxt.config', () => {
    const config = read('nuxt.config.ts')
    expect(config).not.toMatch(/nuxt-auth-utils/)
    expect(config).toMatch(/modules:\s*\[\s*'@nuxt\/fonts'\s*\]/)
  })
})
