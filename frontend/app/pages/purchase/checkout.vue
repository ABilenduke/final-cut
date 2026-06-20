<script setup lang="ts">
import { loadStripe } from '@stripe/stripe-js'
import type { Booking } from '~/types/booking'
import type { ApiErrorResponse } from '~/utils/api'
import { apiFetch } from '~/utils/api'

definePageMeta({
  layout: false,
})

useHead({
  title: 'Payment — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const cart = useCart()
const { activeLocation } = useLocations()
const { isAuthenticated, user } = useAuth()
const { show: showToast } = useToast()
const { setStep } = usePurchaseStep()

// 3 steps total: Seats (1) → Payment (2) → Confirmation (3). Concessions
// live on /food-drink as a browse-only catalog, not in the booking flow.
setStep(2, [1], [1])

// Guard: redirect if no seats in cart
onMounted(() => {
  if (cart.seats.value.length === 0) {
    showToast({ message: 'No seats selected. Please select seats first.', type: 'error' })
    navigateTo('/')
  }
})

// Order reference — stable through the page lifecycle
const orderRef = computed<string>(() => {
  const id = cart.showtime.value?.id ?? ''
  const short = id.slice(-5).toUpperCase() || '00000'
  return `FC-${short}`
})

const changeSeatsHref = computed<string | null>(() => {
  const showtimeId = cart.showtime.value?.id
  return showtimeId ? `/purchase/${showtimeId}` : null
})

// Contact fields: email rides the booking POST (guest receipts); name is
// prefill/display only and is not transmitted anywhere yet.
const contactName = ref('')
const contactEmail = ref('')

// Prefill from authenticated user when available
watchEffect(() => {
  if (user.value && !contactName.value) contactName.value = user.value.name
  if (user.value && !contactEmail.value) contactEmail.value = user.value.email
})

// Promo code handling
function handlePromoApply(code: string) {
  const normalizedCode = code.trim()
  if (!normalizedCode) {
    showToast({ message: 'Please enter a promo code.', type: 'error' })
    return
  }
  cart.applyPromoCode(normalizedCode, 0)
  showToast({
    message: `Promo code "${normalizedCode}" saved — will be validated at checkout.`,
    type: 'info',
  })
}

function handlePromoRemove() {
  cart.removePromoCode()
  showToast({ message: 'Promo code removed.', type: 'info' })
}

// Terms consent — required before submit
const acceptTerms = ref(false)

// Release seats link in hold timer
function handleRelease() {
  if (!cart.showtime.value) {
    cart.clear()
    navigateTo('/')
    return
  }
  const showtimeId = cart.showtime.value.id
  cart.clear()
  showToast({ message: 'Seats released. Starting over.', type: 'info' })
  navigateTo(`/purchase/${showtimeId}`)
}

// Payment bay — page holds the ref so the rail's CTA can trigger submission
const paymentBay = ref<{
  submit: () => Promise<void>
  isSubmitting: boolean
  stripeReady: boolean
} | null>(null)

function requestSubmit() {
  if (!acceptTerms.value) {
    showToast({
      message: 'Please accept the ticketing terms before paying.',
      type: 'error',
    })
    return
  }
  paymentBay.value?.submit()
}

function handleSignInClick() {
  const redirect = encodeURIComponent('/purchase/checkout')
  navigateTo(`/auth/login?redirect=${redirect}`)
}

function buildConfirmationUrl(bookingData: Booking): string {
  const base = `/purchase/confirmation/${bookingData.id}`
  if (bookingData.guestEmail) {
    const params = new URLSearchParams({
      code: bookingData.confirmationCode,
      email: bookingData.guestEmail,
    })
    return `${base}?${params.toString()}`
  }
  return base
}

const submitting = ref(false)

async function handleCheckoutSubmit(payload: { paymentMethodId: string; email?: string; saveCard?: boolean; usingSavedCard?: boolean }) {
  if (submitting.value || !activeLocation.value || !cart.showtime.value) return
  submitting.value = true

  try {
    const body = {
      showtimeId: cart.showtime.value.id,
      // `seat.id` is the UUID the backend keys on; `seat.label` is for display.
      seatIds: cart.seats.value.map(s => s.id),
      paymentMethodId: payload.paymentMethodId,
      promoCode: cart.promoCode.value,
      giftCardCode: cart.giftCardCode.value,
      email: payload.email ?? contactEmail.value ?? null,
      ...(payload.saveCard ? { saveCard: true } : {}),
      ...(payload.usingSavedCard ? { usingSavedCard: true } : {}),
    }

    // Idempotency key for this submit attempt. apiFetch sends it as the
    // Idempotency-Key header and reuses it on its internal 419 retry, so a
    // lost-response/double-submit replays the original booking instead of
    // double-charging. A fresh key per attempt avoids replaying a changed order.
    const idempotencyKey = crypto.randomUUID()

    type BookingAction = { requiresAction: true; clientSecret: string; paymentIntentId: string }
    const response = await apiFetch<{ data: Booking | BookingAction }>(
      `/api/locations/${activeLocation.value.slug}/bookings`,
      { method: 'POST', body, idempotencyKey },
    )

    if ('requiresAction' in response.data && response.data.requiresAction) {
      const { clientSecret, paymentIntentId } = response.data
      const config = useRuntimeConfig()
      const stripe = await loadStripe(config.public.stripePublishableKey as string)
      if (!stripe) {
        showToast({ message: 'Unable to verify payment. Please try again.', type: 'error' })
        return
      }

      const { error: confirmError } = await stripe.handleCardAction(clientSecret)
      if (confirmError) {
        showToast({ message: confirmError.message ?? '3D Secure verification failed.', type: 'error' })
        return
      }

      const confirmResponse = await apiFetch<{ data: Booking }>(
        `/api/locations/${activeLocation.value.slug}/bookings/confirm`,
        { method: 'POST', body: { paymentIntentId } },
      )
      await navigateTo(buildConfirmationUrl(confirmResponse.data))
      return
    }

    await navigateTo(buildConfirmationUrl(response.data))
  } catch (err) {
    const apiError = err as ApiErrorResponse

    switch (apiError.status) {
      case 409: {
        showToast({ message: 'Some seats are no longer available.', type: 'error' })
        await navigateTo(`/purchase/${cart.showtime.value!.id}`)
        break
      }
      case 400: {
        const fieldError = apiError.errors?.find(e => e.field === 'promoCode')
        if (fieldError) {
          cart.removePromoCode()
          showToast({ message: fieldError.message, type: 'error' })
        } else {
          const message = apiError.errors?.[0]?.message ?? 'Please check your order and try again.'
          showToast({ message, type: 'error' })
        }
        break
      }
      case 402:
        showToast({ message: 'Payment declined. Please try another card.', type: 'error' })
        break
      case 410:
        showToast({ message: 'Your session has expired. Please start over.', type: 'error' })
        cart.clear()
        await navigateTo('/')
        break
      default:
        showToast({
          message: 'Something went wrong. Your card was not charged. Please try again.',
          type: 'error',
        })
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <NuxtLayout name="purchase">
    <!-- Hold timer lives outside main so it can render below the fixed header -->
    <template #below-header>
      <CheckoutHoldTimer
        v-if="cart.showtime.value && cart.seats.value.length > 0"
        :time-remaining="cart.timeRemaining.value"
        :auditorium="cart.showtime.value.screenName"
        :seats="cart.seats.value"
        :order-ref="orderRef"
        :change-seats-href="changeSeatsHref"
        @release="handleRelease"
      />
    </template>

    <!-- Header extras: secure-checkout badge + location pill -->
    <template #header-extras>
      <div class="checkout-secure">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" /></svg>
        Secure Checkout
      </div>
      <div v-if="activeLocation" class="checkout-loc">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" /></svg>
        <b>{{ activeLocation.name }}</b>
      </div>
    </template>

    <!-- Right rail replaces the default CartSummary -->
    <template #rail>
      <CheckoutTotalsRail
        :seats="cart.seats.value"
        :promo-code="cart.promoCode.value"
        :promo-discount="cart.promoDiscount.value"
        :gift-card-amount="cart.giftCardAmount.value"
        :subtotal="cart.subtotal.value"
        :total="cart.total.value"
      />
    </template>

    <div class="checkout-page">
    <header class="checkout-page__top">
      <div>
        <div class="checkout-page__eyebrow">Reel 02 · Payment</div>
        <h1 class="checkout-page__title">
          <em>Finish the</em> booking.
        </h1>
      </div>
      <div v-if="cart.showtime.value" class="checkout-page__meta">
        <b>{{ formatDateTime(cart.showtime.value.startTime) }}</b>
        {{ cart.showtime.value.screenName }} · {{ cart.seats.value.length }} seat{{ cart.seats.value.length === 1 ? '' : 's' }}
      </div>
    </header>

    <div class="checkout-page__main">
      <CheckoutOrderCard
        v-if="cart.showtime.value"
        :showtime="cart.showtime.value"
        :seats="cart.seats.value"
        :change-seats-href="changeSeatsHref"
      />

      <CheckoutContactBay
        v-model:full-name="contactName"
        v-model:email="contactEmail"
        :is-authenticated="isAuthenticated"
        @sign-in="handleSignInClick"
      />

      <CheckoutPaymentBay
        ref="paymentBay"
        :email="contactEmail"
        :is-authenticated="isAuthenticated"
        @submit="handleCheckoutSubmit"
        @error="(msg: string) => showToast({ message: msg, type: 'error' })"
      />

      <PromoCode
        :applied-code="cart.promoCode.value"
        :discount="cart.promoDiscount.value"
        @apply="handlePromoApply"
        @remove="handlePromoRemove"
      />

      <CheckoutConfirmBay
        v-model:accept-terms="acceptTerms"
        :total="cart.total.value"
        :time-remaining="cart.timeRemaining.value"
        :submitting="submitting"
        :disabled="!paymentBay?.stripeReady || cart.seats.value.length === 0"
        @submit="requestSubmit"
      />
    </div>
    </div>
  </NuxtLayout>
</template>

<style scoped>
.checkout-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
  padding-top: 2.5rem; /* Compensate for the hold-strip */
}

.checkout-page__top {
  padding: var(--space-xl) 0 var(--space-lg);
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-lg);
  flex-wrap: wrap;
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.checkout-page__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.checkout-page__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.checkout-page__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.25rem);
  line-height: 1;
  letter-spacing: -0.03em;
  text-wrap: balance;
  color: var(--on-surface);
  margin: 0;
}

.checkout-page__title em {
  font-style: italic;
  color: var(--tertiary);
}

.checkout-page__meta {
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
}

.checkout-page__meta b {
  font-family: var(--font-display);
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.04em;
  font-size: 0.9375rem;
  text-transform: none;
  display: block;
  margin-bottom: 0.2rem;
}

.checkout-page__main {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
  min-width: 0;
}

.checkout-secure {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--secondary);
}

.checkout-secure svg {
  width: 0.75rem;
  height: 0.75rem;
}

.checkout-loc {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 0.8125rem;
  padding: 0.375rem 0.625rem;
  background-color: rgba(42, 42, 42, 0.5);
  border-radius: var(--radius-sm);
}

.checkout-loc svg {
  width: 0.875rem;
  height: 0.875rem;
}

.checkout-loc b {
  color: var(--on-surface);
  font-weight: 500;
}

@media (max-width: 40rem) {
  .checkout-secure,
  .checkout-loc {
    display: none;
  }
}
</style>
