<script setup lang="ts">
import { apiFetch } from '~/utils/api'
import type { ApiErrorResponse } from '~/utils/api'

const emit = defineEmits<{
  'submit': [payload: { name: string; email: string; subject: string; message: string }]
}>()

const name = ref('')
const email = ref('')
const subject = ref('')
const message = ref('')
const loading = ref(false)

const errors = ref<Record<string, string>>({})

function validate(): boolean {
  const e: Record<string, string> = {}
  if (!name.value.trim()) e.name = 'Name is required'
  if (!email.value.trim()) {
    e.email = 'Email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    e.email = 'Please enter a valid email'
  }
  if (!subject.value.trim()) e.subject = 'Subject is required'
  if (!message.value.trim()) e.message = 'Message is required'
  errors.value = e
  return Object.keys(e).length === 0
}

async function handleSubmit() {
  if (!validate()) return

  loading.value = true
  try {
    const payload = {
      name: name.value.trim(),
      email: email.value.trim(),
      subject: subject.value.trim(),
      message: message.value.trim(),
    }
    await apiFetch('/api/contact', {
      method: 'POST',
      body: payload,
    })
    emit('submit', payload)
    useToast().show({ message: 'Message sent. We\'ll be in touch.', type: 'success' })
    name.value = ''
    email.value = ''
    subject.value = ''
    message.value = ''
    errors.value = {}
  } catch (err) {
    const apiError = err as ApiErrorResponse
    if (apiError.errors?.[0]?.message) {
      useToast().show({ message: apiError.errors[0].message, type: 'error' })
    } else {
      useToast().show({ message: 'Something went wrong. Please try again.', type: 'error' })
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="contact-form" @submit.prevent="handleSubmit" novalidate>
    <h3 class="contact-form__heading headline-sm">Get in Touch</h3>
    <CvInput
      v-model="name"
      label="Name"
      :error="errors.name"
      required
    />
    <CvInput
      v-model="email"
      label="Email"
      type="email"
      :error="errors.email"
      required
    />
    <CvInput
      v-model="subject"
      label="Subject"
      :error="errors.subject"
      required
    />
    <CvTextarea
      v-model="message"
      label="Message"
      :rows="5"
      :error="errors.message"
      required
    />
    <CvButton
      type="submit"
      :loading="loading"
      :disabled="loading"
    >
      Send Message
    </CvButton>
  </form>
</template>

<style scoped>
.contact-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.contact-form__heading {
  color: var(--on-surface);
  margin: 0;
}
</style>
