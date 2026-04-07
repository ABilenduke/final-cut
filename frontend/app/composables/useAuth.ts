import type { User } from '~/types/user'
import type { ApiErrorResponse } from '~/utils/api'
import { apiFetch, _resetCsrf } from '~/utils/api'

export function useAuth() {
  const user = useState<User | null>('auth:user', () => null)
  const isAuthenticated = computed(() => user.value !== null)
  const loading = ref(false)
  const error = ref<ApiErrorResponse | null>(null)

  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await apiFetch<{ data: User }>('/api/auth/login', {
        method: 'POST',
        body: { email, password },
      })
      user.value = response.data
      _resetCsrf()
    } catch (e) {
      error.value = e as ApiErrorResponse
      throw e
    } finally {
      loading.value = false
    }
  }

  async function register(name: string, email: string, password: string, passwordConfirmation: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await apiFetch<{ data: User }>('/api/auth/register', {
        method: 'POST',
        body: { name, email, password, password_confirmation: passwordConfirmation },
      })
      user.value = response.data
      _resetCsrf()
    } catch (e) {
      error.value = e as ApiErrorResponse
      throw e
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await apiFetch('/api/auth/logout', { method: 'POST' })
      user.value = null
      _resetCsrf()
    } catch (e) {
      const err = e as ApiErrorResponse
      // Server confirmed no session (401/419) — safe to clear locally
      if (err.status === 401 || err.status === 419) {
        user.value = null
        _resetCsrf()
        return
      }
      // Transport failure — session may still be alive server-side
      user.value = null
      _resetCsrf()
      throw e
    }
  }

  async function fetchUser(): Promise<void> {
    try {
      const response = await apiFetch<{ data: User }>('/api/auth/me')
      user.value = response.data
    } catch {
      user.value = null
    }
  }

  async function forgotPassword(email: string): Promise<void> {
    await apiFetch('/api/auth/forgot-password', {
      method: 'POST',
      body: { email },
    })
  }

  async function resetPassword(token: string, email: string, password: string, passwordConfirmation: string): Promise<void> {
    await apiFetch('/api/auth/reset-password', {
      method: 'POST',
      body: { token, email, password, password_confirmation: passwordConfirmation },
    })
  }

  return {
    user, isAuthenticated, loading, error,
    login, register, logout, fetchUser, forgotPassword, resetPassword,
  }
}
