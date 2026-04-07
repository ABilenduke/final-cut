import type { User } from '~/types/user'
import type { ApiErrorResponse } from '~/utils/api'
import { apiFetch } from '~/utils/api'

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
    } catch {
      // Swallow errors — logout should always succeed client-side
    } finally {
      user.value = null
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
