// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  vite: {
    server: {
      allowedHosts: ['finalcut.test'],
    },
  },

  css: ['~/assets/css/main.css'],

  nitro: {
    devStorage: {
      // Nuxt stores ISR payloads under cache:nuxt using the route as the key.
      // The filesystem driver maps ":" to path separators, so "/" and nested
      // routes like "/movies" collide as file-vs-directory paths. Keep this
      // Nuxt runtime cache in memory during dev while preserving ISR behavior.
      'cache:nuxt': { driver: 'memory' },
    },
  },

  app: {
    head: {
      htmlAttrs: { lang: 'en' },
    },
  },

  routeRules: {
    '/': { isr: 1800 },
    '/movies': { isr: 1800 },
    '/movies/**': { isr: 600 },
    '/food-drink': { isr: 1800 },
    '/events': { isr: 900 },
    '/events/**': { isr: 900 },
    '/locations': { isr: 1800 },
    '/locations/**': { isr: 1800 },
    '/blog': { isr: 600 },
    '/blog/**': { isr: 600 },
    '/contact': { prerender: true },
    '/faq': { prerender: true },
    '/accessibility': { prerender: true },
    '/careers': { prerender: true },
    '/private-screenings': { prerender: true },
    '/gift-cards': { isr: 1800 },
    // X-Robots-Tag header keeps these out of search indices; sitemap.exclude
    // (configured below) is the matching sitemap-side opt-out. The robots:
    // false routeRule key is gated behind @nuxtjs/robots which is not installed
    // here — sitemap.exclude is the canonical opt-out for sitemap visibility.
    '/purchase/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/account': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/account/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/auth/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
  },

  runtimeConfig: {
    public: {
      apiBaseUrl: '',              // Laravel API base URL (NUXT_PUBLIC_API_BASE_URL)
      stripePublishableKey: '',    // Stripe publishable key (client-side only)
      siteUrl: '',                 // Base URL for SEO, OG tags
      appTimeZone: 'America/New_York', // Date-only UI timezone (NUXT_PUBLIC_APP_TIME_ZONE)
    },
  },

  components: [
    {
      path: '~/components',
      pathPrefix: false,
    },
  ],

  modules: ['@nuxt/fonts', '@nuxtjs/sitemap'],

  // @nuxtjs/sitemap configuration.
  //
  // site.url is the canonical domain used to prefix <loc> entries in the XML.
  // It is read from the NUXT_SITE_URL environment variable at build/SSR time.
  // Dynamic URLs (movie slugs, event slugs, location slugs, blog post slugs)
  // are sourced from the server route handler at /api/__sitemap__/urls — this
  // is @nuxtjs/sitemap's conventional endpoint name for dynamic URL sources.
  //
  // The `exclude` list is a fallback safety net. The primary exclusion mechanism
  // is `robots: false` on the routeRules above — the sitemap module honours that
  // automatically. The explicit excludes below guard against any future route
  // rule change that might accidentally re-enable these paths.
  sitemap: {
    sources: ['/api/__sitemap__/urls'],
    exclude: [
      '/purchase/**',
      '/account',
      '/account/**',
      '/auth/**',
    ],
  },

  // Site URL for @nuxtjs/sitemap canonical <loc> prefixes.
  // Mapped from NUXT_SITE_URL env var at runtime.
  site: {
    url: process.env.NUXT_SITE_URL ?? 'https://finalcut.test',
  },

  fonts: {
    families: [
      { name: 'Noto Serif', weights: [400, 700] },
      { name: 'Newsreader', weights: [400, 700], styles: ['normal', 'italic'] },
    ],
  },
})
