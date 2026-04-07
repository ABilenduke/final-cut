import { defineVitestConfig } from '@nuxt/test-utils/config'

export default defineVitestConfig({
  test: {
    environment: 'nuxt',
    environmentOptions: {
      nuxt: {
        overrides: {
          runtimeConfig: {
            public: {
              apiBaseUrl: 'https://api.test',
            },
          },
        },
      },
    },
  },
})
