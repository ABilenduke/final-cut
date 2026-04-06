// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  css: ['~/assets/css/main.css'],

  // Route rules (ISR/SSR/prerender) deferred to Plan 13 (E2E & Polish).
  // Target values documented in docs/architecture/SITE_ARCHITECTURE.md.
  // For v1, all routes use default client-side rendering.

  runtimeConfig: {
    public: {
      apiBaseUrl: '',              // Laravel API base URL (NUXT_PUBLIC_API_BASE_URL)
      stripePublishableKey: '',    // Stripe publishable key (client-side only)
      siteUrl: '',                 // Base URL for SEO, OG tags
    },
  },

  modules: ['@nuxt/fonts'],

  fonts: {
    families: [
      { name: 'Noto Serif', weights: [400, 700] },
      { name: 'Newsreader', weights: [400, 700], styles: ['normal', 'italic'] },
    ],
  },
})
