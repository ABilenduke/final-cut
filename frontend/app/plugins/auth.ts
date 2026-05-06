import { hasAuthSessionMarker } from '~/composables/useAuth'

export default defineNuxtPlugin(async () => {
  // Public initial loads should not make a guaranteed-401 /auth/me probe.
  // Auth-gated route middleware still revalidates on direct protected loads.
  if (import.meta.client && hasAuthSessionMarker()) {
    const { fetchUser } = useAuth()
    await fetchUser()
  }
})
