<script setup lang="ts">
import { loadStripe, type Stripe, type StripeCardElement } from '@stripe/stripe-js'
import { apiFetch } from '~/utils/api'

// Account-page add-card flow (admin-v4 Plan 02): the backend mints a
// SetupIntent; the card is collected with Stripe Elements and confirmed
// client-side, attaching it to the user's Stripe Customer. Mirrors the
// GiftCardPaymentModal Elements lifecycle.
const emit = defineEmits<{
  close: []
  added: []
}>()

const open = ref(true)
watch(open, (value) => {
  if (!value) emit('close')
})

const cardMountRef = ref<HTMLElement | null>(null)
let stripe: Stripe | null = null
let cardElement: StripeCardElement | null = null
const stripeReady = ref(false)
const stripeLoadError = ref('')
const cardError = ref('')
const isSubmitting = ref(false)

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

    const cs = getComputedStyle(document.documentElement)
    const token = (name: string): string => cs.getPropertyValue(name).trim()

    const elements = stripe.elements({
      appearance: {
        theme: 'night',
        variables: {
          colorPrimary: token('--secondary'),
          colorBackground: token('--surface-container-low'),
          colorText: token('--on-surface'),
          colorDanger: token('--primary'),
          colorTextPlaceholder: token('--on-tertiary-fixed-variant'),
          fontFamily: 'Newsreader, serif',
          borderRadius: token('--radius-sm'),
        },
      },
    })

    cardElement = elements.create('card', {
      style: {
        base: {
          fontSize: '1rem',
          color: token('--on-surface'),
          fontFamily: 'Newsreader, serif',
          '::placeholder': { color: token('--on-tertiary-fixed-variant') },
        },
        invalid: { color: token('--primary') },
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

async function save(): Promise<void> {
  if (isSubmitting.value || !stripe || !cardElement) return

  isSubmitting.value = true
  cardError.value = ''

  try {
    const { data } = await apiFetch<{ data: { clientSecret: string } }>(
      '/api/account/payment-methods',
      { method: 'POST' },
    )

    const { error } = await stripe.confirmCardSetup(data.clientSecret, {
      payment_method: { card: cardElement },
    })

    if (error) {
      cardError.value = error.message ?? 'Card error. Please check your details.'
      return
    }

    emit('added')
  } catch {
    cardError.value = 'Something went wrong. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <CvModal v-model="open" title="Add a card">
    <div class="pm-add">
      <p v-if="stripeLoadError" class="pm-add__error" role="alert" data-testid="pm-add-error">
        {{ stripeLoadError }}
      </p>
      <template v-else>
        <p class="pm-add__label" aria-hidden="true">Card details</p>
        <div
          ref="cardMountRef"
          class="pm-add__card-mount"
          role="group"
          aria-label="Card details"
        />
        <p v-if="cardError" class="pm-add__error" role="alert" data-testid="pm-add-error">
          {{ cardError }}
        </p>
        <p class="pm-add__note">
          Your card is stored by Stripe — we never see the number.
        </p>
      </template>
    </div>

    <template #footer>
      <CvButton variant="secondary" :disabled="isSubmitting" @click="open = false">
        Cancel
      </CvButton>
      <CvButton
        variant="primary"
        :loading="isSubmitting"
        :disabled="Boolean(stripeLoadError) || !stripeReady"
        data-testid="pm-add-submit"
        @click="save"
      >
        Save card
      </CvButton>
    </template>
  </CvModal>
</template>

<style scoped>
.pm-add {
  display: grid;
  gap: var(--space-md);
}

.pm-add__label {
  margin: 0;
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--tertiary);
}

.pm-add__card-mount {
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border-bottom: 1px solid var(--outline);
  border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}

.pm-add__error {
  margin: 0;
  color: var(--state-danger-text);
  font-size: 0.875rem;
}

.pm-add__note {
  margin: 0;
  color: var(--tertiary);
  font-size: 0.8125rem;
}
</style>
