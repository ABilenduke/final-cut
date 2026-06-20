import { toValue, type MaybeRefOrGetter } from 'vue'
import { buildSeoHead, type SeoInput } from '~/utils/seo'

/**
 * Centralised SEO wiring for a page: title (bare — the global titleTemplate in
 * `app.vue` brands it), description, canonical link, Open Graph + Twitter meta,
 * og:image (with the site-wide fallback), and JSON-LD structured data.
 *
 * Accepts a ref/getter so pages whose data loads asynchronously (e.g.
 * `/events/:slug`) update their tags reactively once the fetch resolves.
 * All logic lives in the pure `buildSeoHead` builder (see `~/utils/seo`); this
 * is only the reactive Nuxt wrapper.
 */
export function useSeo(input: MaybeRefOrGetter<SeoInput>): void {
  const siteUrl = String(useRuntimeConfig().public.siteUrl ?? '')
  const route = useRoute()

  // `buildSeoHead` returns a deliberately strict, unit-tested shape. unhead's
  // input type carries a `data-*` index signature that a *named* type can't
  // satisfy implicitly (inline literals are exempt — that's why the existing
  // per-page useHead calls compile). The runtime object is a valid head; this
  // localized cast bridges the type gap without loosening the builder.
  type HeadInput = Parameters<typeof useHead>[0]
  useHead((() => buildSeoHead(toValue(input), siteUrl, route.path)) as unknown as HeadInput)
}
