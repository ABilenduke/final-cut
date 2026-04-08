<script setup lang="ts">
const props = defineProps<{
  total: number
  isAuthenticated: boolean
}>()

const emit = defineEmits<{
  submit: [payload: { paymentMethodId: string; email?: string; loyaltyOptIn?: boolean }]
  error: [message: string]
}>()

const billingName = ref('')
const email = ref('')
const loyaltyOptIn = ref(false)
const isSubmitting = ref(false)
const formError = ref('')

// Stripe Elements will be initialized when Stripe is available
const stripeReady = ref(false)
const cardError = ref('')

// Placeholder for Stripe integration — actual Stripe Elements initialization
// requires @stripe/stripe-js to be installed and configured.
// For now, we provide the form structure and validation.
const hasCardInput = ref(false)

function validateForm(): boolean {
  if (!billingName.value.trim()) {
    formError.value = 'Billing name is required'
    return false
  }
  if (!props.isAuthenticated && !email.value.trim()) {
    formError.value = 'Email is required for guest checkout'
    return false
  }
  if (!props.isAuthenticated && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    formError.value = 'Please enter a valid email address'
    return false
  }
  formError.value = ''
  return true
}

async function handleSubmit() {
  if (!validateForm()) return
  if (isSubmitting.value) return

  isSubmitting.value = true
  formError.value = ''

  try {
    // In production, this would use stripe.createPaymentMethod()
    // For now, emit with a placeholder paymentMethodId
    const payload: { paymentMethodId: string; email?: string; loyaltyOptIn?: boolean } = {
      paymentMethodId: 'pm_placeholder',
    }

    if (!props.isAuthenticated) {
      payload.email = email.value.trim()
      if (loyaltyOptIn.value) {
        payload.loyaltyOptIn = true
      }
    }

    emit('submit', payload)
  } catch (err) {
    const message = err instanceof Error ? err.message : 'Payment failed. Please try again.'
    formError.value = message
    emit('error', message)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="checkout-form">
    <h3 class="checkout-form__heading">Payment</h3>

    <form class="checkout-form__form" @submit.prevent="handleSubmit">
      <CvInput
        v-model="billingName"
        label="Billing Name"
        placeholder="Full name on card"
        required
      />

      <template v-if="!isAuthenticated">
        <CvInput
          v-model="email"
          type="email"
          label="Email"
          placeholder="For your receipt and booking confirmation"
          required
        />

        <CvCheckbox
          v-model="loyaltyOptIn"
          label="Join Final Cut Rewards (free) — earn points on this purchase"
        />
      </template>

      <!-- Stripe Card Element placeholder -->
      <div class="checkout-form__card-element">
        <label class="checkout-form__card-label">Card Details</label>
        <div class="checkout-form__card-mount" aria-label="Credit card input">
          <!-- Stripe Elements mounts here in production -->
          <div class="checkout-form__card-placeholder">
            Card input (Stripe Elements)
          </div>
        </div>
        <p v-if="cardError" class="checkout-form__card-error" role="alert">
          {{ cardError }}
        </p>
      </div>

      <div v-if="formError" class="checkout-form__error" role="alert" aria-live="assertive">
        {{ formError }}
      </div>

      <div class="checkout-form__total">
        <span class="checkout-form__total-label">Total to pay</span>
        <span class="checkout-form__total-price">{{ formatCurrency(total) }}</span>
      </div>

      <CvButton
        type="submit"
        variant="primary"
        size="lg"
        :loading="isSubmitting"
        :disabled="isSubmitting"
        class="checkout-form__submit"
      >
        Complete Purchase
      </CvButton>
    </form>
  </div>
</template>

<style scoped>
.checkout-form__heading {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--on-surface);
  margin-bottom: var(--space-lg);
}

.checkout-form__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.checkout-form__card-element {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.checkout-form__card-label {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
}

.checkout-form__card-mount {
  height: 3rem;
  padding: var(--space-sm) 0;
  border-bottom: 0.0625rem solid var(--outline);
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.checkout-form__card-mount:focus-within {
  border-bottom-color: var(--secondary);
  box-shadow: 0 0.125rem 0.5rem rgba(218, 199, 105, 0.3);
}

.checkout-form__card-placeholder {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--on-tertiary-fixed-variant);
  line-height: 2;
}

.checkout-form__card-error {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--primary);
  margin: 0;
}

.checkout-form__error {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--primary);
  padding: var(--space-sm);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
}

.checkout-form__total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-md);
  border-top: 0.0625rem solid var(--outline-variant);
}

.checkout-form__total-label {
  font-family: var(--font-display);
  font-size: var(--type-title-lg);
  color: var(--on-surface);
}

.checkout-form__total-price {
  font-family: var(--font-display);
  font-size: var(--type-title-lg);
  color: var(--secondary);
}

.checkout-form__submit {
  width: 100%;
  margin-top: var(--space-sm);
}
</style>
