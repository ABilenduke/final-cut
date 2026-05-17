import { defineVitestConfig } from '@nuxt/test-utils/config'

export default defineVitestConfig({
  test: {
    environment: 'nuxt',
    // Force the forks pool. Default `threads` and `vmThreads` pools share a
    // single Node process and rely on tinypool's worker `unref()` to exit;
    // under Deno's npm-vitest shim that unref doesn't always fire, so the
    // parent process hangs after all tests pass. Forks spawn one child
    // process per worker and exit cleanly on completion — slightly slower
    // startup, but reliable shutdown is non-negotiable.
    pool: 'forks',
  },
})
