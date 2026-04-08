<script setup lang="ts">
import { apiFetch } from '~/utils/api'
import type { ApiErrorResponse } from '~/utils/api'

const emit = defineEmits<{
  'submit': [payload: { eventType: string; preferredDate: string; guestCount: number; name: string; email: string; message: string }]
}>()

const eventTypeOptions = [
  { value: 'birthday', label: 'Birthday Party' },
  { value: 'corporate', label: 'Corporate Event' },
  { value: 'proposal', label: 'Proposal' },
  { value: 'custom', label: 'Custom Event' },
]

const eventType = ref('')
const preferredDate = ref('')
const guestCount = ref<string | number>('')
const name = ref('')
const email = ref('')
const message = ref('')
const loading = ref(false)

const errors = ref<Record<string, string>>({})

function validate(): boolean {
  const e: Record<string, string> = {}
  if (!eventType.value) e.eventType = 'Please select an event type'
  if (!preferredDate.value) e.preferredDate = 'Please select a date'
  if (!guestCount.value || Number(guestCount.value) < 1) e.guestCount = 'Guest count is required'
  if (!name.value.trim()) e.name = 'Name is required'
  if (!email.value.trim()) {
    e.email = 'Email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    e.email = 'Please enter a valid email'
  }
  errors.value = e
  return Object.keys(e).length === 0
}

async function handleSubmit() {
  if (!validate()) return

  loading.value = true
  try {
    const payload = {
      eventType: eventType.value,
      preferredDate: preferredDate.value,
      guestCount: Number(guestCount.value),
      name: name.value.trim(),
      email: email.value.trim(),
      message: message.value.trim(),
    }
    await apiFetch('/api/rentals/inquiry', {
      method: 'POST',
      body: {
        eventType: payload.eventType,
        preferredDate: payload.preferredDate,
        guestCount: payload.guestCount,
        name: payload.name,
        email: payload.email,
        message: payload.message,
      },
    })
    emit('submit', payload)
    useToast().show({ message: 'Inquiry submitted! We\'ll be in touch soon.', type: 'success' })
    eventType.value = ''
    preferredDate.value = ''
    guestCount.value = ''
    name.value = ''
    email.value = ''
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
  <form class="rental-form" @submit.prevent="handleSubmit" novalidate>
    <h3 class="rental-form__heading headline-sm">Request a Screening</h3>
    <CvSelect
      v-model="eventType"
      label="Event Type"
      :options="eventTypeOptions"
      placeholder="Select event type..."
      :error="errors.eventType"
      required
    />
    <CvInput
      v-model="preferredDate"
      label="Preferred Date"
      type="date"
      :error="errors.preferredDate"
      required
    />
    <CvInput
      v-model="guestCount"
      label="Number of Guests"
      type="number"
      placeholder="e.g. 30"
      :error="errors.guestCount"
      required
    />
    <CvInput
      v-model="name"
      label="Your Name"
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
    <CvTextarea
      v-model="message"
      label="Additional Details (optional)"
      :rows="4"
      placeholder="Tell us about your event..."
    />
    <CvButton
      type="submit"
      :loading="loading"
      :disabled="loading"
    >
      Submit Inquiry
    </CvButton>
  </form>
</template>

<style scoped>
.rental-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.rental-form__heading {
  color: var(--on-surface);
  margin: 0;
}
</style>
