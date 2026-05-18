import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const PUBLIC_ROOT = resolve(__dirname, '../../public')
const MANIFEST_PATH = resolve(PUBLIC_ROOT, 'site.webmanifest')
const NUXT_CONFIG = readFileSync(resolve(__dirname, '../../nuxt.config.ts'), 'utf8')

type WebManifest = {
  name: string
  short_name: string
  description?: string
  start_url: string
  scope: string
  display: string
  theme_color: string
  background_color: string
  icons: Array<{
    src: string
    sizes: string
    type: string
  }>
}

function readManifest(): WebManifest {
  return JSON.parse(readFileSync(MANIFEST_PATH, 'utf8')) as WebManifest
}

describe('architecture - site manifest', () => {
  it('publishes a brand-aligned standalone web app manifest', () => {
    expect(existsSync(MANIFEST_PATH)).toBe(true)

    const manifest = readManifest()

    expect(manifest).toMatchObject({
      name: 'Final Cut',
      short_name: 'Final Cut',
      start_url: '/',
      scope: '/',
      display: 'standalone',
      theme_color: '#550000',
      background_color: '#0e0e0e',
    })
    expect(manifest.description).toContain('Final Cut')
  })

  it('references installable PNG icons that are present in public assets', () => {
    const manifest = readManifest()

    expect(manifest.icons).toEqual(expect.arrayContaining([
      { src: '/android-chrome-192x192.png', sizes: '192x192', type: 'image/png' },
      { src: '/android-chrome-512x512.png', sizes: '512x512', type: 'image/png' },
    ]))

    for (const icon of manifest.icons) {
      const iconPath = resolve(PUBLIC_ROOT, icon.src.replace(/^\//, ''))
      expect(existsSync(iconPath)).toBe(true)
    }
  })

  it('links the manifest and browser theme color in Nuxt head metadata', () => {
    expect(NUXT_CONFIG).toContain("{ rel: 'manifest', href: '/site.webmanifest' }")
    expect(NUXT_CONFIG).toContain("{ name: 'theme-color', content: '#550000' }")
  })
})
