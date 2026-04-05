import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:3000',
    // All environments use self-signed TLS certs (make certs)
    ignoreHTTPSErrors: true,
  },
  ...(process.env.BASE_URL
    ? {}
    : {
        webServer: {
          command: 'node .output/server/index.mjs',
          url: 'http://localhost:3000',
          reuseExistingServer: !process.env.CI,
        },
      }),
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
})
