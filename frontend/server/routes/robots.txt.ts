import { defineEventHandler } from 'h3'
import { useRuntimeConfig } from '#imports'

export default defineEventHandler((event) => {
  const config = useRuntimeConfig(event)
  const siteUrl = ((config.public.siteUrl as string) || 'https://finalcut.test').replace(/\/+$/, '')

  // Use the underlying Node response directly — the auto-imported `setHeader`
  // resolves to h3@2 in dev (pulled in by @nuxt/test-utils) but the runtime
  // event is h3@1 shape, causing a setResponseHeader crash.
  event.node.res.setHeader('content-type', 'text/plain; charset=utf-8')
  event.node.res.setHeader('cache-control', 'public, max-age=3600')

  return [
    'User-agent: *',
    'Disallow: /purchase/',
    'Disallow: /account',
    'Disallow: /auth/',
    '',
    `Sitemap: ${siteUrl}/sitemap.xml`,
    '',
  ].join('\n')
})
