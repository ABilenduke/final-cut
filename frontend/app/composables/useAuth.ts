import type { User } from '~/types/user'

/**
 * Auth state composable stub.
 * Full implementation (login, register, logout, fetchUser) will be added
 * when the auth integration plan is executed.
 */
export function useAuth() {
  const user = useState<User | null>('auth:user', () => null)
  const isAuthenticated = computed(() => user.value !== null)

  return {
    user,
    isAuthenticated,
  }
}
