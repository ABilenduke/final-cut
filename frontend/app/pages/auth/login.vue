<script setup lang="ts">
definePageMeta({ layout: 'blank', middleware: 'guest' })
useHead({ title: 'Sign In — Final Cut', meta: [{ name: 'robots', content: 'noindex' }] })

const route = useRoute()
const { login, loading, error } = useAuth()
const { show } = useToast()

const email = ref('')
const password = ref('')

function getFieldError(field: string): string | undefined {
  return error.value?.errors.find(e => e.field === field)?.message
}

async function handleSubmit() {
  try {
    await login(email.value, password.value)
    await navigateTo((route.query.redirect as string) || '/account')
  } catch {
    const generic = error.value?.errors.find(e => !e.field)
    if (generic) {
      show({ message: generic.message, type: 'error' })
    }
  }
}
</script>

<template>
  <div class="auth-page">
    <h1 class="auth-page__title">Sign In</h1>

    <form class="auth-page__form" @submit.prevent="handleSubmit">
      <CvInput
        v-model="email"
        type="email"
        label="Email"
        placeholder="you@example.com"
        required
        :error="getFieldError('email')"
        :disabled="loading"
      />

      <CvInput
        v-model="password"
        type="password"
        label="Password"
        required
        :error="getFieldError('password')"
        :disabled="loading"
      />

      <div class="auth-page__actions">
        <CvButton
          type="submit"
          variant="primary"
          :loading="loading"
          :disabled="loading"
        >
          Sign In
        </CvButton>
      </div>
    </form>

    <div class="auth-page__links">
      <NuxtLink to="/auth/forgot-password" class="auth-page__link">
        Forgot password?
      </NuxtLink>
      <NuxtLink to="/auth/register" class="auth-page__link">
        Create account
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
