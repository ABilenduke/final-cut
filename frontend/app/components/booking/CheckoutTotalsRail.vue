<script setup lang="ts">
import type { BookingSeat } from '~/types/booking'

const props = defineProps<{
  seats: readonly BookingSeat[]
  promoCode: string | null
  promoDiscount: number
  giftCardAmount: number
  subtotal: number
  total: number
}>()

const seatsTotal = computed<number>(() =>
  props.seats.reduce((sum, s) => sum + s.price, 0),
)

const seatSectionLabel = computed<string>(() => {
  if (props.seats.length === 0) return 'No seats selected'
  const sections = new Set(props.seats.map(s => s.section))
  return sections.size === 1
    ? `${props.seats.length} × ${[...sections][0]}`
    : `${props.seats.length} × mixed sections`
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
        <div class="totals-rail__rule" />
        <div class="totals-rail__line">
          <span>Subtotal</span>
          <span class="totals-rail__v">{{ formatCurrency(subtotal) }}</span>
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
          <span class="totals-rail__grand-v">{{ formatCurrency(total) }}</span>
        </div>
      </dl>
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
  height: var(--border-hairline);
  background-color: rgb(var(--outline-variant-rgb) / 0.3);
  margin: 0.3rem 0;
}

.totals-rail__grand {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-sm);
  border-top: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.3);
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
  border-radius: var(--radius-full);
  background-color: rgb(var(--secondary-rgb) / 0.1);
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
