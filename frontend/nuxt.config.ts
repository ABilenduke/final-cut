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
    '/gift-cards/bulk': { prerender: true },
    // X-Robots-Tag header keeps these out of search indices. The matching
    // sitemap opt-out lives in server/routes/sitemap.xml.ts EXCLUDED_PREFIXES.
    '/purchase/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/account': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/account/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
    '/auth/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
  },

  runtimeConfig: {
    public: {
      apiBaseUrl: '',              // Laravel API base URL (NUXT_PUBLIC_API_BASE_URL)
      stripePublishableKey: '',    // Stripe publishable key (client-side only)
      siteUrl: 'https://finalcut.test', // Base URL for SEO, OG tags, sitemap (NUXT_PUBLIC_SITE_URL)
      appTimeZone: 'America/New_York', // Date-only UI timezone (NUXT_PUBLIC_APP_TIME_ZONE)
    },
  },

  components: [
    {
      path: '~/components',
      pathPrefix: false,
    },
  ],

  modules: ['@nuxt/fonts'],

  fonts: {
    families: [
      { name: 'Noto Serif', weights: [400, 700] },
      { name: 'Newsreader', weights: [400, 700], styles: ['normal', 'italic'] },
    ],
  },
})
