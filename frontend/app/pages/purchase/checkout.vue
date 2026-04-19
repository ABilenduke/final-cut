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

setStep(3, [1, 2], [1, 2])

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

// Contact fields — rendered for design parity; not yet wired to the booking POST
const contactName = ref('')
const contactEmail = ref('')
const contactPhone = ref('')
const contactReelId = ref('')

// Prefill from authenticated user when available
watchEffect(() => {
  if (user.value && !contactName.value) contactName.value = user.value.name
  if (user.value && !contactEmail.value) contactEmail.value = user.value.email
})

// Snacks were chosen on /purchase/snacks; this page just shows what's in the cart.
const snacksHref = '/purchase/snacks'
const hasSnacks = computed<boolean>(() =>
  cart.foodItems.value.length > 0 || cart.pairing.value !== null,
)

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
const subscribeReel = ref(false)

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

async function handleCheckoutSubmit(payload: { paymentMethodId: string; email?: string; loyaltyOptIn?: boolean }) {
  if (submitting.value || !activeLocation.value || !cart.showtime.value) return
  submitting.value = true

  try {
    const body = {
      showtimeId: cart.showtime.value.id,
      seatIds: cart.seats.value.map(s => s.seatId),
      foodItems: cart.foodItems.value.map(f => ({ itemId: f.itemId, quantity: f.quantity })),
      paymentMethodId: payload.paymentMethodId,
      promoCode: cart.promoCode.value,
      giftCardCode: cart.giftCardCode.value,
      email: payload.email ?? contactEmail.value ?? null,
      loyaltyOptIn: payload.loyaltyOptIn ?? false,
    }

    const response = await apiFetch<{
      data: Booking
      requiresAction?: boolean
      clientSecret?: string
    }>(
      `/api/locations/${activeLocation.value.slug}/bookings`,
      { method: 'POST', body },
    )

    if (response.requiresAction && response.clientSecret) {
      const config = useRuntimeConfig()
      const stripe = await loadStripe(config.public.stripePublishableKey as string)
      if (!stripe) {
        showToast({ message: 'Unable to verify payment. Please try again.', type: 'error' })
        return
      }

      const { error: confirmError } = await stripe.handleCardAction(response.clientSecret)
      if (confirmError) {
        showToast({ message: confirmError.message ?? '3D Secure verification failed.', type: 'error' })
        return
      }

      const confirmResponse = await apiFetch<{ data: Booking }>(
        `/api/locations/${activeLocation.value.slug}/bookings/confirm`,
        { method: 'POST', body: { paymentIntentId: response.clientSecret.split('_secret_')[0] } },
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
        :food-items="cart.foodItems.value"
        :promo-code="cart.promoCode.value"
        :promo-discount="cart.promoDiscount.value"
        :gift-card-amount="cart.giftCardAmount.value"
        :subtotal="cart.subtotal.value"
        :total="cart.total.value"
        :time-remaining="cart.timeRemaining.value"
        :submitting="submitting"
        :disabled="!paymentBay?.stripeReady"
        @submit="requestSubmit"
      />
    </template>

    <div class="checkout-page">
    <header class="checkout-page__top">
      <div>
        <div class="checkout-page__eyebrow">Reel 03 · Payment</div>
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
        v-model:phone="contactPhone"
        v-model:reel-society-id="contactReelId"
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

      <!-- Snacks summary — read-only echo of what was added on /purchase/snacks -->
      <section class="snacks-summary" aria-label="Concessions summary">
        <header class="snacks-summary__head">
          <div>
            <div class="snacks-summary__n">§ 03</div>
            <h2 class="snacks-summary__title">Your <em>tray.</em></h2>
          </div>
          <NuxtLink :to="snacksHref" class="snacks-summary__edit">
            ← Edit snacks
          </NuxtLink>
        </header>

        <p v-if="!hasSnacks" class="snacks-summary__empty">
          You skipped the bar. Add something to your tray on the snacks step before payment.
        </p>

        <ul v-else class="snacks-summary__list">
          <li v-if="cart.pairing.value" class="snacks-summary__item snacks-summary__item--pairing">
            <span class="snacks-summary__q">1×</span>
            <div class="snacks-summary__nm">
              {{ cart.pairing.value.title }}
              <span class="snacks-summary__nm-sub">
                Pairing · pay at the bar on collection
              </span>
            </div>
            <span class="snacks-summary__pr">{{ formatCurrency(cart.pairingPrice.value) }}</span>
          </li>
          <li v-for="f in cart.foodItems.value" :key="f.itemId" class="snacks-summary__item">
            <span class="snacks-summary__q">{{ f.quantity }}×</span>
            <div class="snacks-summary__nm">{{ f.name }}</div>
            <span class="snacks-summary__pr">{{ formatCurrency(f.unitPrice * f.quantity) }}</span>
          </li>
        </ul>
      </section>

      <PromoCode
        v-model:accept-terms="acceptTerms"
        v-model:subscribe-reel="subscribeReel"
        :applied-code="cart.promoCode.value"
        :discount="cart.promoDiscount.value"
        @apply="handlePromoApply"
        @remove="handlePromoRemove"
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
  border-bottom: 0.0625rem solid rgba(87, 66, 62, 0.2);
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
  border-radius: 0.125rem;
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

/* Snacks summary (read-only echo of /purchase/snacks selections) */
.snacks-summary {
  background-color: var(--surface-container);
  border-radius: var(--radius-card);
  padding: var(--space-lg);
  border: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.snacks-summary__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-md);
  padding-bottom: var(--space-sm);
  border-bottom: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2);
}

.snacks-summary__n {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
}

.snacks-summary__title {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.1;
  margin: 0.2rem 0 0;
  color: var(--on-surface);
}

.snacks-summary__title em {
  font-style: italic;
  color: var(--tertiary);
}

.snacks-summary__edit {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--secondary);
  text-decoration: none;
  align-self: center;
}

.snacks-summary__edit:hover,
.snacks-summary__edit:focus-visible {
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}

.snacks-summary__empty {
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  margin: 0;
}

.snacks-summary__list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  margin: 0;
  padding: 0;
}

.snacks-summary__item {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-sm);
  align-items: center;
  padding: 0.4rem 0;
  border-bottom: 0.0625rem dashed rgb(var(--outline-variant-rgb) / 0.25);
}

.snacks-summary__item:last-child {
  border-bottom: none;
}

.snacks-summary__q {
  font-family: var(--font-display);
  font-size: 0.875rem;
  color: var(--secondary);
  font-variant-numeric: tabular-nums;
  font-weight: 500;
  min-width: 1.5rem;
  text-align: center;
}

.snacks-summary__nm {
  font-family: var(--font-display);
  font-size: 0.875rem;
  color: var(--on-surface);
  letter-spacing: -0.005em;
}

.snacks-summary__nm-sub {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  display: block;
  margin-top: 0.1rem;
}

.snacks-summary__pr {
  font-family: var(--font-display);
  font-size: 0.875rem;
  color: var(--tertiary);
  font-variant-numeric: tabular-nums;
}
</style>
