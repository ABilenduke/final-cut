import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const FOOTER = readFileSync(resolve(__dirname, '../../app/components/layout/SiteFooter.vue'), 'utf8')
const PAGES_ROOT = resolve(__dirname, '../../app/pages')

function pagePathForRoute(route: string): string {
  const cleanRoute = route.split('?')[0]!.replace(/^\/+|\/+$/g, '')
  return cleanRoute ? resolve(PAGES_ROOT, `${cleanRoute}.vue`) : resolve(PAGES_ROOT, 'index.vue')
}

describe('architecture — footer routes', () => {
  it('only links to routes with matching pages', () => {
    const routes = [...FOOTER.matchAll(/(?:href|to):?\s*=\s*['"]([^'"]+)['"]/g)]
      .map(match => match[1]!)
      .filter(route => route.startsWith('/'))

    const missing = routes.filter(route => !existsSync(pagePathForRoute(route)))

    expect(missing).toEqual([])
  })
})
