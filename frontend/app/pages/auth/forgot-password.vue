<script setup lang="ts">
definePageMeta({ layout: 'blank', middleware: 'guest' })
useHead({ title: 'Forgot Password — Final Cut', meta: [{ name: 'robots', content: 'noindex' }] })

import type { ApiErrorResponse } from '~/utils/api'

const { forgotPassword } = useAuth()
const { show } = useToast()

const email = ref('')
const sent = ref(false)
const loading = ref(false)
const error = ref<ApiErrorResponse | null>(null)

function getFieldError(field: string): string | undefined {
  return error.value?.errors.find(e => e.field === field)?.message
}

async function handleSubmit() {
  error.value = null
  loading.value = true
  try {
    await forgotPassword(email.value)
    sent.value = true
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
    <h1 class="auth-page__title">Forgot Password</h1>

    <div v-if="sent" class="auth-page__success">
      <p class="auth-page__success-text">
        If an account exists for <strong>{{ email }}</strong>, you will receive a
        password reset link shortly.
      </p>
    </div>

    <form v-else class="auth-page__form" @submit.prevent="handleSubmit">
      <p class="auth-page__description">
        Enter your email address and we'll send you a link to reset your password.
      </p>

      <CvInput
        v-model="email"
        type="email"
        label="Email"
        placeholder="you@example.com"
        required
        :error="getFieldError('email')"
        :disabled="loading"
      />

      <div class="auth-page__actions">
        <CvButton
          type="submit"
          variant="primary"
          :loading="loading"
          :disabled="loading"
        >
          Send Reset Link
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

.auth-page__description {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
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

.auth-page__success {
  text-align: center;
}

.auth-page__success-text {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
  line-height: 1.5;
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
