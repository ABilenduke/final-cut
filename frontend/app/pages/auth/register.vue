<script setup lang="ts">
definePageMeta({ layout: 'blank', middleware: 'guest' })
useHead({ title: 'Create Account — Final Cut', meta: [{ name: 'robots', content: 'noindex' }] })

const { register, loading, error } = useAuth()
const { show } = useToast()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const termsAccepted = ref(false)

const passwordMismatchError = ref('')
const termsError = ref('')

function getFieldError(field: string): string | undefined {
  return error.value?.errors.find(e => e.field === field)?.message
}

function validate(): boolean {
  passwordMismatchError.value = ''
  termsError.value = ''

  let valid = true

  if (password.value !== passwordConfirmation.value) {
    passwordMismatchError.value = 'Passwords do not match'
    valid = false
  }

  if (!termsAccepted.value) {
    termsError.value = 'You must accept the terms and conditions'
    valid = false
  }

  return valid
}

async function handleSubmit() {
  if (!validate()) return

  try {
    await register(name.value, email.value, password.value, passwordConfirmation.value)
    await navigateTo('/account')
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
    <h1 class="auth-page__title">Create Account</h1>

    <form class="auth-page__form" @submit.prevent="handleSubmit">
      <CvInput
        v-model="name"
        type="text"
        label="Name"
        placeholder="Your name"
        required
        :error="getFieldError('name')"
        :disabled="loading"
      />

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

      <CvInput
        v-model="passwordConfirmation"
        type="password"
        label="Confirm Password"
        required
        :error="passwordMismatchError || getFieldError('password_confirmation')"
        :disabled="loading"
      />

      <CvCheckbox
        v-model="termsAccepted"
        label="I accept the terms and conditions"
        required
        :error="termsError"
        :disabled="loading"
      />

      <div class="auth-page__actions">
        <CvButton
          type="submit"
          variant="primary"
          :loading="loading"
          :disabled="loading"
        >
          Create Account
        </CvButton>
      </div>
    </form>

    <div class="auth-page__links">
      <NuxtLink to="/auth/login" class="auth-page__link">
        Already have an account?
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
