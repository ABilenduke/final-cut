<script setup lang="ts">
import { DEFAULT_OG_IMAGE, absoluteUrl, organizationSchema } from '~/utils/seo'
import { safeJsonLd } from '~/utils/safeJsonLd'

const siteUrl = String(useRuntimeConfig().public.siteUrl ?? '')
const defaultOgImage = absoluteUrl(DEFAULT_OG_IMAGE, siteUrl)

// Site-wide SEO defaults. Per-page `useSeo`/`useHead` calls register later and
// override by tag key (unhead dedupes meta by name/property — last wins).
useHead({
  // Idempotent brand suffix: pages whose title already contains "Final Cut"
  // (every current page) are left untouched; a bare page title gets branded.
  // This is what lets pages migrate to bare titles without a flag-day rename.
  titleTemplate: (title?: string) =>
    !title
      ? 'Final Cut — Movie Theatre'
      : title.includes('Final Cut')
        ? title
        : `${title} — Final Cut`,
  meta: [
    { property: 'og:site_name', content: 'Final Cut' },
    { property: 'og:type', content: 'website' },
    { property: 'og:locale', content: 'en_US' },
    { name: 'twitter:card', content: 'summary_large_image' },
    // Fallback social-share image for any page that supplies none.
    ...(defaultOgImage
      ? [
          { property: 'og:image', content: defaultOgImage },
          { name: 'twitter:image', content: defaultOgImage },
        ]
      : []),
  ],
  script: [
    // Brand-level Organization, emitted once for the whole site.
    { type: 'application/ld+json', innerHTML: safeJsonLd(organizationSchema(siteUrl)) },
  ],
})
</script>

<template>
  <div>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>
