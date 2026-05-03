import { hasAuthSessionMarker } from '~/composables/useAuth'

export default defineNuxtRouteMiddleware(async () => {
  const { isAuthenticated, fetchUser } = useAuth()

  if (import.meta.client && !isAuthenticated.value && hasAuthSessionMarker()) {
    await fetchUser()
  }

  if (isAuthenticated.value) {
    return navigateTo('/account')
  }
})
