export default defineNuxtRouteMiddleware(() => {
  // Stub-safe: useAuth may not exist yet during early development
  try {
    const { isAuthenticated } = useAuth()
    if (isAuthenticated.value) {
      return navigateTo('/account')
    }
  }
  catch {
    // If useAuth doesn't exist yet, allow access
  }
})
