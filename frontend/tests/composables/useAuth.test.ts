import { describe, it, expect, beforeEach, vi } from 'vitest'

vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(),
  useApiFetch: vi.fn(),
}))

import { apiFetch } from '~/utils/api'
const mockApiFetch = vi.mocked(apiFetch)

import { useAuth } from '~/composables/useAuth'

const MOCK_USER = {
  id: '1',
  email: 'test@example.com',
  name: 'Test User',
  avatarUrl: null,
  loyaltyPoints: 0,
  loyaltyTier: 'member' as const,
  premierExpiry: null,
  createdAt: '2026-01-01T00:00:00Z',
}

describe('useAuth', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    const { user } = useAuth()
    user.value = null
  })

  it('login sets user on success', async () => {
    mockApiFetch.mockResolvedValue({ data: MOCK_USER })

    const { user, login } = useAuth()
    await login('test@example.com', 'password123')

    expect(user.value).toEqual(MOCK_USER)
  })

  it('login throws and sets error on failure', async () => {
    const apiError = { errors: [{ message: 'Invalid credentials' }], status: 401 }
    mockApiFetch.mockRejectedValue(apiError)

    const { error, login } = useAuth()

    await expect(login('bad@example.com', 'wrong')).rejects.toEqual(apiError)
    expect(error.value).toEqual(apiError)
  })

  it('login sends correct payload to apiFetch', async () => {
    mockApiFetch.mockResolvedValue({ data: MOCK_USER })

    const { login } = useAuth()
    await login('test@example.com', 'password123')

    expect(mockApiFetch).toHaveBeenCalledWith('/api/auth/login', {
      method: 'POST',
      body: { email: 'test@example.com', password: 'password123' },
    })
  })

  it('register sets user on success', async () => {
    mockApiFetch.mockResolvedValue({ data: MOCK_USER })

    const { user, register } = useAuth()
    await register('Test User', 'test@example.com', 'password123', 'password123')

    expect(user.value).toEqual(MOCK_USER)
  })

  it('register sends correct payload with password_confirmation', async () => {
    mockApiFetch.mockResolvedValue({ data: MOCK_USER })

    const { register } = useAuth()
    await register('Test User', 'test@example.com', 'password123', 'password123')

    expect(mockApiFetch).toHaveBeenCalledWith('/api/auth/register', {
      method: 'POST',
      body: {
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123',
        password_confirmation: 'password123',
      },
    })
  })

  it('logout clears user even if API fails', async () => {
    mockApiFetch.mockRejectedValue(new Error('Network error'))

    const { user, logout } = useAuth()
    user.value = MOCK_USER

    await logout()

    expect(user.value).toBeNull()
  })

  it('fetchUser restores session on success', async () => {
    mockApiFetch.mockResolvedValue({ data: MOCK_USER })

    const { user, fetchUser } = useAuth()
    await fetchUser()

    expect(user.value).toEqual(MOCK_USER)
    expect(mockApiFetch).toHaveBeenCalledWith('/api/auth/me')
  })

  it('fetchUser clears user on 401', async () => {
    mockApiFetch.mockRejectedValue({ errors: [{ message: 'Unauthenticated' }], status: 401 })

    const { user, fetchUser } = useAuth()
    user.value = MOCK_USER

    await fetchUser()

    expect(user.value).toBeNull()
  })

  it('forgotPassword calls correct endpoint with email', async () => {
    mockApiFetch.mockResolvedValue({ data: { success: true } })

    const { forgotPassword } = useAuth()
    await forgotPassword('test@example.com')

    expect(mockApiFetch).toHaveBeenCalledWith('/api/auth/forgot-password', {
      method: 'POST',
      body: { email: 'test@example.com' },
    })
  })

  it('resetPassword sends all params correctly', async () => {
    mockApiFetch.mockResolvedValue({ data: { success: true } })

    const { resetPassword } = useAuth()
    await resetPassword('token123', 'test@example.com', 'newpass', 'newpass')

    expect(mockApiFetch).toHaveBeenCalledWith('/api/auth/reset-password', {
      method: 'POST',
      body: {
        token: 'token123',
        email: 'test@example.com',
        password: 'newpass',
        password_confirmation: 'newpass',
      },
    })
  })

  it('isAuthenticated is reactive', () => {
    const { user, isAuthenticated } = useAuth()

    expect(isAuthenticated.value).toBe(false)

    user.value = MOCK_USER
    expect(isAuthenticated.value).toBe(true)

    user.value = null
    expect(isAuthenticated.value).toBe(false)
  })

  it('loading is true during API call', async () => {
    let resolvePromise: (value: any) => void
    mockApiFetch.mockImplementation(() => new Promise((resolve) => {
      resolvePromise = resolve
    }))

    const { loading, login } = useAuth()

    expect(loading.value).toBe(false)

    const loginPromise = login('test@example.com', 'password123')

    expect(loading.value).toBe(true)

    resolvePromise!({ data: MOCK_USER })
    await loginPromise

    expect(loading.value).toBe(false)
  })
})
