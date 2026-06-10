<script setup lang="ts">
import { loadStripe, type Stripe, type StripeCardElement } from '@stripe/stripe-js'
import { apiFetch } from '~/utils/api'
import type { ApiErrorResponse } from '~/utils/api'
import type { GiftCard, PurchaseGiftCardData } from '~/types/gift-card'

// Final payment step for the gift-card composer (admin-v3 Plan 03). Card
// collection mirrors CheckoutPaymentBay's Stripe Elements setup; the
// purchase/3DS/confirm contract mirrors the booking checkout.
const props = defineProps<{
  payload: Omit<PurchaseGiftCardData, 'paymentMethodId' | 'idempotencyKey'>
  amountLabel: string
}>()

const emit = defineEmits<{
  close: []
  purchased: [giftCard: GiftCard]
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

interface GiftCardAction {
  requiresAction: true
  clientSecret: string
  paymentIntentId: string
}

async function pay(): Promise<void> {
  if (isSubmitting.value || !stripe || !cardElement) return

  isSubmitting.value = true
  cardError.value = ''

  try {
    const { paymentMethod, error: stripeError } = await stripe.createPaymentMethod({
      type: 'card',
      card: cardElement,
    })

    if (stripeError || !paymentMethod) {
      cardError.value = stripeError?.message ?? 'Card error. Please check your details.'
      return
    }

    // Fresh key per attempt — apiFetch sends it as the Idempotency-Key
    // header so a lost response replays the original purchase.
    const idempotencyKey = crypto.randomUUID()

    const response = await apiFetch<{ data: GiftCard | GiftCardAction }>(
      '/api/gift-cards/purchase',
      {
        method: 'POST',
        body: { ...props.payload, paymentMethodId: paymentMethod.id },
        idempotencyKey,
      },
    )

    if ('requiresAction' in response.data && response.data.requiresAction) {
      const { clientSecret, paymentIntentId } = response.data

      const { error: actionError } = await stripe.handleCardAction(clientSecret)
      if (actionError) {
        cardError.value = actionError.message ?? '3D Secure verification failed.'
        return
      }

      const confirmResponse = await apiFetch<{ data: GiftCard }>(
        '/api/gift-cards/confirm',
        { method: 'POST', body: { paymentIntentId } },
      )
      emit('purchased', confirmResponse.data)
      return
    }

    emit('purchased', response.data as GiftCard)
  } catch (err) {
    const apiError = err as ApiErrorResponse

    if (apiError.status === 402) {
      cardError.value = 'Payment declined. Please try another card.'
    } else if (apiError.status === 400 && apiError.errors?.[0]?.message) {
      cardError.value = apiError.errors[0].message
    } else {
      cardError.value = 'Something went wrong. Your card was not charged. Please try again.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <CvModal v-model="open" title="Payment">
    <div class="gc-pay">
      <dl class="gc-pay__summary">
        <div class="gc-pay__summary-row">
          <dt>Gift card</dt>
          <dd>{{ amountLabel }}</dd>
        </div>
        <div class="gc-pay__summary-row">
          <dt>To</dt>
          <dd>{{ payload.recipientName }} · {{ payload.recipientEmail }}</dd>
        </div>
      </dl>

      <p v-if="stripeLoadError" class="gc-pay__error" role="alert" data-testid="gc-pay-error">
        {{ stripeLoadError }}
      </p>
      <template v-else>
        <p id="gc-pay-card-label" class="gc-pay__label" aria-hidden="true">Card details</p>
        <div
          ref="cardMountRef"
          class="gc-pay__card-mount"
          role="group"
          aria-label="Card details"
        />
        <p v-if="cardError" class="gc-pay__error" role="alert" data-testid="gc-pay-error">
          {{ cardError }}
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
        data-testid="gc-pay-submit"
        @click="pay"
      >
        Pay {{ amountLabel }}
      </CvButton>
    </template>
  </CvModal>
</template>

<style scoped>
.gc-pay {
  display: grid;
  gap: var(--space-md);
}

.gc-pay__summary {
  display: grid;
  gap: var(--space-xs);
  margin: 0;
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border-radius: var(--radius-sm);
}

.gc-pay__summary-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
}

.gc-pay__summary-row dt {
  color: var(--tertiary);
  font-size: 0.875rem;
}

.gc-pay__summary-row dd {
  margin: 0;
  color: var(--on-surface);
  font-size: 0.875rem;
  text-align: right;
}

.gc-pay__label {
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--tertiary);
}

.gc-pay__card-mount {
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border-bottom: 1px solid var(--outline);
  border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}

.gc-pay__error {
  margin: 0;
  color: var(--state-danger-text);
  font-size: 0.875rem;
}
</style>
