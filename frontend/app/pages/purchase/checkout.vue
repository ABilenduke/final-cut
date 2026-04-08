<script setup lang="ts">
import type { Booking } from '~/types/booking'
import type { ApiErrorResponse } from '~/utils/api'
import { apiFetch } from '~/utils/api'
import { menuData } from '~/data/menu'

definePageMeta({
  layout: 'purchase',
})

useHead({
  title: 'Checkout — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const cart = useCart()
const { activeLocation } = useLocations()
const { isAuthenticated } = useAuth()
const { show: showToast } = useToast()
const { setStep } = usePurchaseStep()

// Set step indicator
setStep(2, [1], [1])

// Guard: redirect if no seats in cart
onMounted(() => {
  if (cart.seats.value.length === 0) {
    showToast({ message: 'No seats selected. Please select seats first.', type: 'error' })
    navigateTo('/')
  }
})

// Food pre-order state
const selectedFoodItems = ref<Array<{ itemId: string; quantity: number }>>([])

function handleFoodUpdate(items: Array<{ itemId: string; quantity: number }>) {
  const currentQuantities = new Map<string, number>()
  for (const existing of cart.foodItems.value) {
    currentQuantities.set(existing.itemId, existing.quantity)
  }

  const nextQuantities = new Map<string, number>()
  for (const item of items) {
    nextQuantities.set(item.itemId, item.quantity)
  }

  // Remove quantities that were reduced or deleted
  for (const [itemId, currentQty] of currentQuantities) {
    const nextQty = nextQuantities.get(itemId) ?? 0
    for (let i = 0; i < currentQty - nextQty; i++) {
      cart.removeFoodItem(itemId)
    }
  }

  // Add quantities that are new or increased
  for (const [itemId, nextQty] of nextQuantities) {
    const currentQty = currentQuantities.get(itemId) ?? 0
    const addCount = nextQty - currentQty
    if (addCount <= 0) continue

    const menuItem = menuData.find(m => m.id === itemId)
    if (!menuItem) continue

    for (let i = 0; i < addCount; i++) {
      cart.addFoodItem(menuItem.id, menuItem.name, menuItem.price)
    }
  }

  selectedFoodItems.value = items
}

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

// Checkout submission
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
      email: payload.email ?? null,
      loyaltyOptIn: payload.loyaltyOptIn ?? false,
    }

    const response = await apiFetch<{ data: Booking }>(
      `/api/locations/${activeLocation.value.slug}/bookings`,
      { method: 'POST', body },
    )

    await navigateTo(`/purchase/confirmation/${response.data.id}`)
  } catch (err) {
    const apiError = err as ApiErrorResponse

    switch (apiError.status) {
      case 409: {
        showToast({ message: 'Some seats are no longer available.', type: 'error' })
        await navigateTo(`/purchase/${cart.showtime.value!.id}`)
        break
      }
      case 400: {
        // Validation errors (invalid promo code, gift card, etc.)
        const fieldError = apiError.errors.find(e => e.field === 'promoCode')
        if (fieldError) {
          cart.removePromoCode()
          showToast({ message: fieldError.message, type: 'error' })
        } else {
          const message = apiError.errors[0]?.message ?? 'Please check your order and try again.'
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
  <div class="checkout-page">
    <div class="checkout-page__layout">
      <!-- Left column (65%) -->
      <div class="checkout-page__main">
        <!-- Order summary -->
        <section class="checkout-page__section">
          <h2 class="checkout-page__section-title">Order Summary</h2>
          <div v-if="cart.showtime.value" class="checkout-page__showtime">
            <h3 class="checkout-page__movie">{{ cart.showtime.value.movieTitle }}</h3>
            <p class="checkout-page__meta">
              <time :datetime="cart.showtime.value.startTime">{{ formatDateTime(cart.showtime.value.startTime) }}</time>
              · {{ cart.showtime.value.screenName }}
            </p>
          </div>
          <ul class="checkout-page__seats">
            <li
              v-for="seat in cart.seats.value"
              :key="seat.seatId"
              class="checkout-page__seat-item"
            >
              <span>Seat {{ seat.seatId }} ({{ seat.section }})</span>
              <span>{{ formatCurrency(seat.price) }}</span>
            </li>
          </ul>
        </section>

        <!-- Food pre-order -->
        <section class="checkout-page__section">
          <FoodPreOrderPanel
            :menu-items="menuData"
            :selected-items="selectedFoodItems"
            @update="handleFoodUpdate"
          />
        </section>

        <!-- Promo code -->
        <section class="checkout-page__section">
          <PromoCode
            :applied-code="cart.promoCode.value"
            @apply="handlePromoApply"
            @remove="handlePromoRemove"
          />
        </section>
      </div>

      <!-- Right column (35%) -->
      <div class="checkout-page__sidebar">
        <CheckoutForm
          :total="cart.total.value"
          :is-authenticated="isAuthenticated"
          @submit="handleCheckoutSubmit"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkout-page__layout {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

@media (min-width: 60rem) {
  .checkout-page__layout {
    display: grid;
    grid-template-columns: 65fr 35fr;
    gap: var(--space-2xl);
  }
}

.checkout-page__main {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.checkout-page__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.checkout-page__section-title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--on-surface);
}

.checkout-page__showtime {
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
}

.checkout-page__movie {
  font-family: var(--font-display);
  font-size: var(--type-title-lg);
  color: var(--on-surface);
}

.checkout-page__meta {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
  margin-top: var(--space-xs);
}

.checkout-page__seats {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.checkout-page__seat-item {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
}

.checkout-page__sidebar {
  position: sticky;
  top: 5rem;
  align-self: start;
}

@media (max-width: 59.999rem) {
  .checkout-page {
    padding-bottom: 4.5rem;
  }
}
</style>
