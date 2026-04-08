<script setup lang="ts">
import type { Booking } from '~/types/booking'
import { apiFetch } from '~/utils/api'

definePageMeta({
  layout: 'purchase',
})

useHead({
  title: 'Booking Confirmed — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const route = useRoute()
const bookingId = route.params.bookingId as string

const cart = useCart()
const { show: showToast } = useToast()
const { setStep } = usePurchaseStep()

// Set step indicator — final step, no navigation back
setStep(3, [1, 2, 3], [])

const booking = ref<Booking | null>(null)
const loading = ref(true)
const error = ref('')

// Booking lookup state
const lookupCode = ref('')
const lookupEmail = ref('')
const lookupLoading = ref(false)
const lookupError = ref('')

onMounted(async () => {
  cart.clear()

  try {
    const response = await apiFetch<{ data: Booking }>(`/api/bookings/${bookingId}`)
    booking.value = response.data
  } catch {
    error.value = 'Unable to load booking details.'
    showToast({ message: 'Unable to load booking details.', type: 'error' })
  } finally {
    loading.value = false
  }
})

async function handleLookup() {
  if (!lookupCode.value.trim() || !lookupEmail.value.trim()) {
    lookupError.value = 'Please enter both confirmation code and email.'
    return
  }

  lookupLoading.value = true
  lookupError.value = ''

  try {
    const response = await apiFetch<{ data: Booking }>('/api/bookings/lookup', {
      query: {
        confirmation_code: lookupCode.value.trim(),
        email: lookupEmail.value.trim(),
      },
    })
    booking.value = response.data
    error.value = ''
  } catch {
    lookupError.value = 'Booking not found. Please check your confirmation code and email.'
  } finally {
    lookupLoading.value = false
  }
}
</script>

<template>
  <div class="confirm-page">
    <template v-if="loading">
      <div class="confirm-page__loading">
        <CvSkeletonLoader variant="card" height="30rem" />
      </div>
    </template>

    <template v-else-if="booking">
      <BookingConfirmation :booking="booking" />
    </template>

    <template v-else>
      <div class="confirm-page__fallback">
        <p v-if="error" class="confirm-page__error">{{ error }}</p>

        <div class="confirm-page__lookup">
          <h2 class="confirm-page__lookup-title">Find My Booking</h2>
          <form class="confirm-page__lookup-form" @submit.prevent="handleLookup">
            <CvInput
              v-model="lookupCode"
              label="Confirmation Code"
              placeholder="e.g. CVF-A3X9K2"
              :error="lookupError"
              required
            />
            <CvInput
              v-model="lookupEmail"
              type="email"
              label="Email Address"
              placeholder="Email used during checkout"
              required
            />
            <CvButton
              type="submit"
              variant="primary"
              :loading="lookupLoading"
            >
              Find Booking
            </CvButton>
          </form>
        </div>

        <NuxtLink to="/" class="confirm-page__home-link">
          Back to Home
        </NuxtLink>
      </div>
    </template>
  </div>
</template>

<style scoped>
.confirm-page {
  max-width: 40rem;
  margin-inline: auto;
  padding: var(--space-lg) 0;
}

.confirm-page__loading {
  padding: var(--space-3xl) 0;
}

.confirm-page__fallback {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
  align-items: center;
}

.confirm-page__error {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--primary);
  text-align: center;
}

.confirm-page__lookup {
  width: 100%;
  max-width: 25rem;
}

.confirm-page__lookup-title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--on-surface);
  text-align: center;
  margin-bottom: var(--space-lg);
}

.confirm-page__lookup-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.confirm-page__home-link {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--secondary);
  text-decoration: none;
}

.confirm-page__home-link:hover {
  text-decoration: underline;
}
</style>
