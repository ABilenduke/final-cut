export default defineNuxtRouteMiddleware(async (to: { fullPath: string }) => {
  const { isAuthenticated, fetchUser } = useAuth()

  if (import.meta.client && !isAuthenticated.value) {
    await fetchUser()
  }

  if (!isAuthenticated.value) {
    return navigateTo(`/auth/login?redirect=${encodeURIComponent(to.fullPath)}`)
  }
})
