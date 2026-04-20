<script setup lang="ts">
import { loadStripe } from '@stripe/stripe-js'
import type { Stripe, StripeCardElement } from '@stripe/stripe-js'

type Method = 'card' | 'paypal' | 'gift' | 'cash'

const props = defineProps<{
  email: string
  isAuthenticated: boolean
}>()

const emit = defineEmits<{
  submit: [payload: { paymentMethodId: string; email?: string; loyaltyOptIn?: boolean }]
  error: [message: string]
}>()

const activeMethod = ref<Method>('card')
const loyaltyOptIn = ref(false)
const saveCard = ref(true)
const billingZip = ref('')
const billingCountry = ref('US')
const formError = ref('')
const cardError = ref('')
const isSubmitting = ref(false)

const cardMountRef = ref<HTMLElement | null>(null)
let stripe: Stripe | null = null
let cardElement: StripeCardElement | null = null
const stripeReady = ref(false)
const stripeLoadError = ref('')

onMounted(async () => {
  const config = useRuntimeConfig()
  const publishableKey = config.public.stripePublishableKey as string

  if (!publishableKey) {
    stripeLoadError.value = 'Payment is not configured. Please contact support.'
    return
  }

  try {
    stripe = await loadStripe(publishableKey)
    if (!stripe) {
      stripeLoadError.value = 'Unable to load payment system. Please try again.'
      return
    }

    const elements = stripe.elements({
      appearance: {
        theme: 'night',
        variables: {
          colorPrimary: '#DAC769',
          colorBackground: '#1c1b1b',
          colorText: '#E5E2E1',
          colorDanger: '#FFB4A8',
          fontFamily: 'Newsreader, serif',
          borderRadius: '0.125rem',
          colorTextPlaceholder: '#A89F91',
        },
      },
    })

    cardElement = elements.create('card', {
      style: {
        base: {
          fontSize: '1rem',
          color: '#E5E2E1',
          fontFamily: 'Newsreader, serif',
          '::placeholder': { color: '#A89F91' },
        },
        invalid: { color: '#FFB4A8' },
      },
      hidePostalCode: true,
    })

    if (cardMountRef.value) {
      cardElement.mount(cardMountRef.value)
      stripeReady.value = true

      cardElement.on('change', (event) => {
        cardError.value = event.error?.message ?? ''
      })
    }
  } catch {
    stripeLoadError.value = 'Unable to load payment system. Please try again.'
  }
})

onBeforeUnmount(() => {
  cardElement?.destroy()
})

function setMethod(m: Method): void {
  if (m !== 'card') return // Other methods are visually present but disabled
  activeMethod.value = m
}

function validate(): boolean {
  if (!props.isAuthenticated && !props.email.trim()) {
    formError.value = 'Email is required for guest checkout'
    return false
  }
  if (!props.isAuthenticated && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(props.email)) {
    formError.value = 'Please enter a valid email address'
    return false
  }
  if (!stripeReady.value) {
    formError.value = 'Payment system is not ready. Please wait or refresh.'
    return false
  }
  formError.value = ''
  return true
}

async function submit(): Promise<void> {
  if (!validate()) return
  if (isSubmitting.value) return
  if (!stripe || !cardElement) return

  isSubmitting.value = true
  formError.value = ''
  cardError.value = ''

  try {
    const { paymentMethod, error: stripeError } = await stripe.createPaymentMethod({
      type: 'card',
      card: cardElement,
      billing_details: {
        ...(props.isAuthenticated ? {} : { email: props.email.trim() }),
        address: billingZip.value ? { postal_code: billingZip.value, country: billingCountry.value } : undefined,
      },
    })

    if (stripeError) {
      cardError.value = stripeError.message ?? 'Card error. Please check your details.'
      isSubmitting.value = false
      return
    }

    if (!paymentMethod) {
      formError.value = 'Unable to process card. Please try again.'
      isSubmitting.value = false
      return
    }

    const payload: { paymentMethodId: string; email?: string; loyaltyOptIn?: boolean } = {
      paymentMethodId: paymentMethod.id,
    }

    if (!props.isAuthenticated) {
      payload.email = props.email.trim()
      if (loyaltyOptIn.value) payload.loyaltyOptIn = true
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

defineExpose({ submit, isSubmitting, stripeReady })
</script>

<template>
  <section class="bay">
    <header class="bay__header">
      <div>
        <div class="bay__number">§ 02</div>
        <h2 class="bay__title">Payment <em>method.</em></h2>
        <p class="bay__sub">Processed via Stripe · 3D Secure where required.</p>
      </div>
      <span class="bay__badge">PCI-DSS · TLS 1.3</span>
    </header>

    <div class="payment-bay__methods" role="tablist" aria-label="Payment method">
      <button
        type="button"
        role="tab"
        class="method"
        :class="{ 'method--active': activeMethod === 'card' }"
        :aria-selected="activeMethod === 'card'"
        @click="setMethod('card')"
      >
        <span class="method__lbl">Credit / Debit</span>
        <span class="method__name">Card</span>
        <span class="method__marks">
          <span class="method__brand">VISA</span>
          <span class="method__brand">MC</span>
          <span class="method__brand">AMEX</span>
          <span class="method__brand">DISC</span>
        </span>
      </button>
      <button
        type="button"
        role="tab"
        class="method method--disabled"
        aria-selected="false"
        :aria-disabled="true"
        disabled
        title="PayPal coming soon"
      >
        <span class="method__lbl">Balance / Bank</span>
        <span class="method__name">PayPal</span>
        <span class="method__marks"><span class="method__brand">Coming soon</span></span>
      </button>
      <button
        type="button"
        role="tab"
        class="method method--disabled"
        aria-selected="false"
        :aria-disabled="true"
        disabled
        title="Gift card redemption coming soon"
      >
        <span class="method__lbl">Redeem</span>
        <span class="method__name">Gift Card</span>
        <span class="method__marks"><span class="method__brand">Coming soon</span></span>
      </button>
      <button
        type="button"
        role="tab"
        class="method method--disabled"
        aria-selected="false"
        :aria-disabled="true"
        disabled
        title="Pay on Arrival coming soon"
      >
        <span class="method__lbl">At the counter</span>
        <span class="method__name">Pay on Arrival</span>
        <span class="method__marks"><span class="method__brand">Coming soon</span></span>
      </button>
    </div>

    <div v-show="activeMethod === 'card'" class="checkout-fields payment-bay__card">
      <div class="payment-bay__card-field">
        <label class="payment-bay__label">Card details <span class="payment-bay__req">*</span></label>
        <div
          v-if="stripeLoadError"
          class="payment-bay__card-error"
          role="alert"
        >
          {{ stripeLoadError }}
        </div>
        <div
          v-else
          ref="cardMountRef"
          class="payment-bay__card-mount"
          aria-label="Credit card input"
        />
        <p v-if="cardError" class="payment-bay__card-error" role="alert">
          {{ cardError }}
        </p>
      </div>

      <div class="checkout-row-2">
        <CvInput
          v-model="billingZip"
          label="Billing ZIP / Postal"
          placeholder="94103"
          autocomplete="postal-code"
        />
        <div class="payment-bay__country-field">
          <label class="payment-bay__label">Country</label>
          <select v-model="billingCountry" class="payment-bay__country">
            <option value="US">United States</option>
            <option value="CA">Canada</option>
            <option value="GB">United Kingdom</option>
            <option value="FR">France</option>
            <option value="DE">Germany</option>
            <option value="JP">Japan</option>
          </select>
        </div>
      </div>

      <CvCheckbox
        v-if="isAuthenticated"
        v-model="saveCard"
        label="Save this card to my Reel Society account for faster checkout next time."
      />
      <CvCheckbox
        v-else
        v-model="loyaltyOptIn"
        label="Join Final Cut Rewards (free) — earn points on this purchase."
      />
    </div>

    <div v-if="formError" class="payment-bay__error" role="alert" aria-live="assertive">
      {{ formError }}
    </div>
  </section>
</template>

<style scoped>
.payment-bay__methods {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.5rem;
  margin-bottom: var(--space-lg);
}

.method {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.9rem 0.95rem;
  border: 0.0625rem solid rgba(87, 66, 62, 0.4);
  border-radius: 0.125rem;
  background-color: var(--surface-container-low);
  cursor: pointer;
  text-align: left;
  position: relative;
  font-family: var(--font-body);
  color: var(--on-surface);
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard);
}

.method:hover:not(.method--disabled) {
  border-color: rgba(218, 199, 105, 0.4);
}

.method--active {
  border-color: var(--secondary);
  background-color: rgba(218, 199, 105, 0.06);
}

.method--active::after {
  content: '';
  position: absolute;
  top: 0.55rem;
  right: 0.55rem;
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
  background-color: var(--secondary);
}

.method--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.method__lbl {
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.method--active .method__lbl {
  color: var(--secondary);
}

.method__name {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 500;
  color: var(--on-surface);
  letter-spacing: -0.005em;
  line-height: 1;
}

.method__marks {
  display: flex;
  gap: 0.3rem;
  align-items: center;
  margin-top: 0.2rem;
}

.method__brand {
  font-family: var(--font-display);
  font-size: 0.6875rem;
  letter-spacing: 0.1em;
  padding: 0.15rem 0.35rem;
  background-color: var(--surface-container-high);
  border-radius: 0.125rem;
  color: var(--tertiary);
  font-variant-numeric: tabular-nums;
}

.payment-bay__card {
  gap: var(--space-md);
}

.payment-bay__card-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.payment-bay__label {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.payment-bay__req {
  color: var(--secondary);
  margin-left: 0.2rem;
}

.payment-bay__card-mount {
  min-height: 3rem;
  padding: var(--space-sm) 0;
  border-bottom: 0.0625rem solid var(--outline);
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.payment-bay__card-mount:focus-within {
  border-bottom-color: var(--secondary);
  box-shadow: 0 0.125rem 0.5rem rgba(218, 199, 105, 0.3);
}

.payment-bay__card-error {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--primary);
  margin: 0;
}

.payment-bay__country-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.payment-bay__country {
  width: 100%;
  height: 3rem;
  padding: var(--space-sm) 0;
  background: transparent;
  border: none;
  border-bottom: 0.0625rem solid var(--outline);
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
  line-height: 1.6;
  appearance: none;
  cursor: pointer;
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.payment-bay__country:focus-visible {
  outline: none;
  border-bottom-color: var(--secondary);
  box-shadow: 0 0.125rem 0.5rem rgba(218, 199, 105, 0.3);
}

.payment-bay__error {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--primary);
  padding: var(--space-sm);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
  margin-top: var(--space-md);
}

@media (max-width: 68.75rem) {
  .payment-bay__methods {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 40rem) {
  .payment-bay__methods {
    grid-template-columns: 1fr;
  }
}
</style>
