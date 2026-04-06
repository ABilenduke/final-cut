export default defineNuxtRouteMiddleware((to: { fullPath: string }) => {
  // Stub-safe: useAuth may not exist yet during early development
  try {
    const { isAuthenticated } = useAuth()
    if (!isAuthenticated.value) {
      return navigateTo(`/auth/login?redirect=${encodeURIComponent(to.fullPath)}`)
    }
  }
  catch {
    return navigateTo('/auth/login')
  }
})
