export default defineNuxtPlugin(async () => {
  // Hydrate client-side auth state from the Laravel Sanctum session
  // cookie before route middleware runs. Without this, a page reload
  // or direct navigation to an auth-protected route drops the
  // useState auth:user ref back to null and kicks authenticated
  // users out via the auth middleware.
  if (import.meta.client) {
    const { fetchUser } = useAuth()
    await fetchUser()
  }
})
