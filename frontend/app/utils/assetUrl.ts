/**
 * Resolve a public-asset reference into a full URL.
 *
 * Handles three shapes:
 *   - absolute URL (`http://`, `https://`) or `data:`/`blob:`/protocol-relative
 *     (`//`) → returned unchanged. Backend already-resolved URLs (Filament
 *     uploads, TMDB posters, seeded concession photos) all flow through here.
 *   - root-relative app path (`/images/...`) → returned unchanged. Still served
 *     by the Nuxt app; Nuxt build assets are deliberately not on the CDN.
 *   - bare relative path (`concessions/foo.webp`) → prefixed with the configured
 *     `cdnBaseUrl`. The frontend static menu fallback uses this shape so a
 *     single env var swaps the asset host.
 *
 * Falsy input (`null`, `undefined`, empty string) returns an empty string —
 * components rendering `<img :src="assetUrl(x)">` get a no-op rather than a
 * broken request.
 *
 * Reads the CDN base from `useRuntimeConfig().public.cdnBaseUrl` lazily so the
 * helper can be called from SSR or client without a top-level config dependency.
 */
export function assetUrl(value: string | null | undefined): string {
  if (!value) {
    return ''
  }

  if (
    value.startsWith('http://') ||
    value.startsWith('https://') ||
    value.startsWith('//') ||
    value.startsWith('data:') ||
    value.startsWith('blob:')
  ) {
    return value
  }

  if (value.startsWith('/')) {
    return value
  }

  const base = (useRuntimeConfig().public.cdnBaseUrl as string | undefined) ?? ''

  if (!base) {
    return value
  }

  return `${base.replace(/\/$/, '')}/${value}`
}
