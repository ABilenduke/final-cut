<script setup lang="ts">
definePageMeta({ layout: 'blank', middleware: 'guest' })
useHead({ title: 'Reset Password — Final Cut', meta: [{ name: 'robots', content: 'noindex' }] })

import type { ApiErrorResponse } from '~/utils/api'

const route = useRoute()
const { resetPassword } = useAuth()
const { show } = useToast()

const token = typeof route.query.token === 'string' ? route.query.token : ''
const email = typeof route.query.email === 'string' ? route.query.email : ''

if (!token || !email) {
  show({ message: 'Invalid reset link. Please request a new one.', type: 'error' })
  await navigateTo('/auth/forgot-password')
}

const password = ref('')
const passwordConfirmation = ref('')
const passwordMismatchError = ref('')
const loading = ref(false)
const error = ref<ApiErrorResponse | null>(null)

function getFieldError(field: string): string | undefined {
  return error.value?.errors.find(e => e.field === field)?.message
}

function validate(): boolean {
  passwordMismatchError.value = ''

  if (password.value !== passwordConfirmation.value) {
    passwordMismatchError.value = 'Passwords do not match'
    return false
  }

  return true
}

async function handleSubmit() {
  if (!validate()) return

  error.value = null
  loading.value = true
  try {
    await resetPassword(token, email, password.value, passwordConfirmation.value)
    show({ message: 'Password reset successfully', type: 'success' })
    await navigateTo('/auth/login')
  } catch (e) {
    const apiError = e as ApiErrorResponse | undefined
    if (apiError?.errors) {
      error.value = apiError
    }
    const generic = error.value?.errors.find(e => !e.field)
    if (generic) {
      show({ message: generic.message, type: 'error' })
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <h1 class="auth-page__title">Reset Password</h1>

    <form class="auth-page__form" @submit.prevent="handleSubmit">
      <CvInput
        v-model="password"
        type="password"
        label="New Password"
        required
        :error="getFieldError('password')"
        :disabled="loading"
      />

      <CvInput
        v-model="passwordConfirmation"
        type="password"
        label="Confirm Password"
        required
        :error="passwordMismatchError || getFieldError('password_confirmation')"
        :disabled="loading"
      />

      <div class="auth-page__actions">
        <CvButton
          type="submit"
          variant="primary"
          :loading="loading"
          :disabled="loading"
        >
          Reset Password
        </CvButton>
      </div>
    </form>

    <div class="auth-page__links">
      <NuxtLink to="/auth/login" class="auth-page__link">
        Back to login
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  width: 100%;
}

.auth-page__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-md);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  text-align: center;
}

.auth-page__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.auth-page__actions {
  margin-top: var(--space-sm);
}

.auth-page__links {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
}

.auth-page__link {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--secondary);
  text-decoration: none;
}

.auth-page__link:hover {
  text-decoration: underline;
}
</style>
