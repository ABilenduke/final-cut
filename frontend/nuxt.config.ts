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

  routeRules: {
    '/blog/**': { isr: 600 },
    '/contact': { prerender: true },
    '/faq': { prerender: true },
    '/accessibility': { prerender: true },
    '/careers': { prerender: true },
  },

  runtimeConfig: {
    public: {
      apiBaseUrl: '',              // Laravel API base URL (NUXT_PUBLIC_API_BASE_URL)
      stripePublishableKey: '',    // Stripe publishable key (client-side only)
      siteUrl: '',                 // Base URL for SEO, OG tags
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
