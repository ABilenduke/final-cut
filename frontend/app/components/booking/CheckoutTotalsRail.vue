<script setup lang="ts">
import type { BookingSeat } from '~/types/booking'
import type { CartFoodItem } from '~/composables/useCart'

const props = defineProps<{
  seats: readonly BookingSeat[]
  foodItems: readonly CartFoodItem[]
  promoCode: string | null
  promoDiscount: number
  giftCardAmount: number
  subtotal: number
  total: number
  timeRemaining: number
  /** Reflects the parent page's in-flight state so the CTA can show a spinner / disable. */
  submitting?: boolean
  /** Disables the CTA (e.g. Stripe not ready yet) without showing a spinner. */
  disabled?: boolean
}>()

const emit = defineEmits<{
  submit: []
}>()

const seatsTotal = computed<number>(() =>
  props.seats.reduce((sum, s) => sum + s.price, 0),
)

const foodTotal = computed<number>(() =>
  props.foodItems.reduce((sum, f) => sum + f.unitPrice * f.quantity, 0),
)

const foodCount = computed<number>(() =>
  props.foodItems.reduce((sum, f) => sum + f.quantity, 0),
)

/** Fixed per-order booking fee, in cents (display-only; backend is authoritative). */
const BOOKING_FEE = 150
/** CA sales tax rate for display purposes. */
const TAX_RATE = 0.0725

const taxAmount = computed<number>(() => Math.round(props.subtotal * TAX_RATE))

const grandTotal = computed<number>(() =>
  Math.max(0, props.subtotal + BOOKING_FEE + taxAmount.value - props.promoDiscount - props.giftCardAmount),
)

const seatSectionLabel = computed<string>(() => {
  if (props.seats.length === 0) return 'No seats selected'
  const sections = new Set(props.seats.map(s => s.section))
  return sections.size === 1
    ? `${props.seats.length} × ${[...sections][0]}`
    : `${props.seats.length} × mixed sections`
})

const formattedHold = computed<string>(() => {
  const mins = Math.floor(Math.max(0, props.timeRemaining) / 60)
  const secs = Math.max(0, props.timeRemaining) % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})
</script>

<template>
  <div class="totals-rail">
    <section class="bay totals-rail__bay">
      <header class="bay__header totals-rail__header">
        <div>
          <div class="bay__number">§ Ω</div>
          <h2 class="bay__title totals-rail__title">Order <em>total.</em></h2>
        </div>
      </header>

      <dl class="totals-rail__lines" aria-live="polite">
        <div class="totals-rail__line">
          <span>
            {{ seatSectionLabel }}
            <em v-if="seats.length">· Adult admission</em>
          </span>
          <span class="totals-rail__v">{{ formatCurrency(seatsTotal) }}</span>
        </div>
        <div class="totals-rail__line" :class="{ 'totals-rail__line--muted': foodCount === 0 }">
          <span>
            Concessions
            <em>{{ foodCount === 0 ? 'No items' : `${foodCount} item${foodCount > 1 ? 's' : ''} · collect at bar` }}</em>
          </span>
          <span class="totals-rail__v">{{ formatCurrency(foodTotal) }}</span>
        </div>
        <div class="totals-rail__rule" />
        <div class="totals-rail__line">
          <span>Subtotal</span>
          <span class="totals-rail__v">{{ formatCurrency(subtotal) }}</span>
        </div>
        <div class="totals-rail__line">
          <span>Booking fee<em>Per order</em></span>
          <span class="totals-rail__v">{{ formatCurrency(BOOKING_FEE) }}</span>
        </div>
        <div class="totals-rail__line">
          <span>Tax · estimated</span>
          <span class="totals-rail__v">{{ formatCurrency(taxAmount) }}</span>
        </div>
        <div v-if="promoDiscount > 0" class="totals-rail__line">
          <span>
            Member discount
            <em v-if="promoCode">{{ promoCode }}</em>
          </span>
          <span class="totals-rail__v totals-rail__v--neg">−{{ formatCurrency(promoDiscount) }}</span>
        </div>
        <div v-if="giftCardAmount > 0" class="totals-rail__line">
          <span>Gift card</span>
          <span class="totals-rail__v totals-rail__v--neg">−{{ formatCurrency(giftCardAmount) }}</span>
        </div>
        <div class="totals-rail__grand">
          <span>Total due<small>USD</small></span>
          <span class="totals-rail__grand-v">{{ formatCurrency(grandTotal) }}</span>
        </div>
      </dl>

      <button
        type="button"
        class="totals-rail__pay"
        :disabled="disabled || submitting || seats.length === 0"
        @click="emit('submit')"
      >
        <span>{{ submitting ? 'Processing…' : 'Confirm & pay' }}</span>
        <span class="totals-rail__pay-amt">{{ formatCurrency(grandTotal) }}</span>
      </button>

      <p class="totals-rail__note">
        By paying, you authorize Final Cut Ltd. to charge your card.
        Seats release if payment does not complete within <b>{{ formattedHold }}</b>.
      </p>

      <div class="totals-rail__trust">
        <span>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" /></svg>
          TLS 1.3
        </span>
        <span>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6z" /></svg>
          PCI-DSS
        </span>
        <span>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 17l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" /></svg>
          3-D Secure
        </span>
      </div>
    </section>

    <section class="bay totals-rail__upsell">
      <div class="totals-rail__upsell-inner">
        <div class="totals-rail__upsell-badge" aria-hidden="true">★</div>
        <div>
          <div class="totals-rail__upsell-title">Join Reel Society</div>
          <p class="totals-rail__upsell-copy">
            Save this card, unlock $2/ticket off, and get 48h early access to 70mm engagements.
            <NuxtLink to="/account" class="totals-rail__upsell-link">Learn more →</NuxtLink>
          </p>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.totals-rail {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.totals-rail__bay {
  padding: var(--space-lg);
}

.totals-rail__header {
  padding-bottom: var(--space-sm);
  margin-bottom: var(--space-md);
}

.totals-rail__title {
  font-size: 1.375rem;
}

.totals-rail__lines {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin: 0;
}

.totals-rail__line {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--tertiary);
  align-items: baseline;
  gap: var(--space-sm);
}

.totals-rail__line em {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  font-style: normal;
  display: block;
  margin-top: 0.1rem;
}

.totals-rail__line--muted {
  opacity: 0.5;
}

.totals-rail__v {
  font-family: var(--font-display);
  color: var(--on-surface);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
}

.totals-rail__v--neg {
  color: var(--secondary);
}

.totals-rail__rule {
  height: 0.0625rem;
  background-color: rgba(87, 66, 62, 0.3);
  margin: 0.3rem 0;
}

.totals-rail__grand {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-sm);
  border-top: 0.0625rem solid rgba(218, 199, 105, 0.3);
  font-family: var(--font-body);
  font-size: 1rem;
  color: var(--on-surface);
  gap: var(--space-sm);
}

.totals-rail__grand small {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.12em;
  margin-left: 0.4rem;
}

.totals-rail__grand-v {
  font-family: var(--font-display);
  font-size: 2rem;
  color: var(--secondary);
  letter-spacing: -0.02em;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.totals-rail__pay {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 3.5rem;
  padding: 0 1.25rem;
  margin-top: var(--space-md);
  border-radius: 0.125rem;
  background-color: var(--secondary);
  color: var(--surface);
  border: none;
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  width: 100%;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    transform var(--duration-micro) var(--ease-standard);
}

.totals-rail__pay:hover:not(:disabled) {
  background-color: #e6d585;
}

.totals-rail__pay:active:not(:disabled) {
  transform: scale(0.99);
}

.totals-rail__pay:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.totals-rail__pay-amt {
  font-variant-numeric: tabular-nums;
}

.totals-rail__note {
  margin: 0.75rem 0 0;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  line-height: 1.5;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.04em;
}

.totals-rail__note b {
  color: var(--secondary);
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.totals-rail__trust {
  display: flex;
  gap: var(--space-md);
  padding-top: var(--space-sm);
  margin-top: var(--space-md);
  border-top: 0.0625rem solid rgba(87, 66, 62, 0.2);
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  align-items: center;
  flex-wrap: wrap;
}

.totals-rail__trust span {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.totals-rail__trust svg {
  width: 0.75rem;
  height: 0.75rem;
  color: var(--secondary);
}

.totals-rail__upsell {
  padding: var(--space-md) var(--space-lg);
}

.totals-rail__upsell-inner {
  display: flex;
  gap: var(--space-md);
  align-items: flex-start;
}

.totals-rail__upsell-badge {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background-color: rgba(218, 199, 105, 0.1);
  display: grid;
  place-items: center;
  color: var(--secondary);
  flex-shrink: 0;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 1.25rem;
}

.totals-rail__upsell-title {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--on-surface);
  font-weight: 500;
  letter-spacing: -0.005em;
  margin-bottom: 0.2rem;
}

.totals-rail__upsell-copy {
  margin: 0;
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--tertiary);
  line-height: 1.5;
}

.totals-rail__upsell-link {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}
</style>
