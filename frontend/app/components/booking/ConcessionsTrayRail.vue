<script setup lang="ts">
import type { Showtime } from '~/types/showtime'
import type { BookingSeat } from '~/types/booking'
import type { CartFoodItem } from '~/composables/useCart'
import type { ProgrammePairing } from '~/types/programme-pairing'

const props = defineProps<{
  showtime: Showtime | null
  seats: readonly BookingSeat[]
  foodItems: readonly CartFoodItem[]
  pairing: ProgrammePairing | null
  pairingPrice: number
  pairingSavings: number
}>()

const emit = defineEmits<{
  removePairing: []
  removeItem: [itemId: string]
  continue: []
  skip: []
}>()

const seatsTotal = computed<number>(() =>
  props.seats.reduce((sum, s) => sum + s.price, 0),
)

const foodTotal = computed<number>(() =>
  props.foodItems.reduce((sum, f) => sum + f.unitPrice * f.quantity, 0),
)

// Programme Pairings are charged at the bar on collection (see the
// checkout summary copy "pay at the bar on collection"). They are
// deliberately excluded from the authoritative running total so the
// number the user sees here matches what Stripe will charge on the
// checkout step. The pairing price and savings still render on their
// own rows below as editorial context.
const grandTotal = computed<number>(() =>
  Math.max(0, seatsTotal.value + foodTotal.value),
)

const itemCount = computed<number>(() => {
  const food = props.foodItems.reduce((sum, f) => sum + f.quantity, 0)
  return food + (props.pairing ? 1 : 0)
})

const seatLabel = computed<string>(() => {
  if (props.seats.length === 0) return 'No seats selected'
  const rows = new Set(props.seats.map(s => s.seatId.match(/^([A-Z]+)/)?.[1] ?? '').filter(Boolean))
  const rowLabel = rows.size === 1 ? `Row ${[...rows][0]}` : `${rows.size} rows`
  const seatNumbers = props.seats.map(s => s.seatId.replace(/^[A-Z]+/, '')).join(', ')
  return `${rowLabel} · ${seatNumbers}`
})

const ticketsLabel = computed<string>(() => {
  const n = props.seats.length
  if (n === 0) return 'No tickets'
  return `${n} × ticket${n === 1 ? '' : 's'}`
})

const isEmpty = computed<boolean>(() => props.foodItems.length === 0 && !props.pairing)
</script>

<template>
  <div class="tray">
    <section class="tray__bay">
      <!-- Mini show summary -->
      <div v-if="props.showtime" class="tray__show">
        <div class="tray__poster" aria-hidden="true" />
        <div class="tray__info">
          <span class="tray__title">{{ props.showtime.movieTitle }}</span>
          <span class="tray__sub">{{ props.showtime.screenName }}</span>
          <div class="tray__row">{{ seatLabel }}</div>
        </div>
      </div>

      <header class="tray__header">
        <div>
          <div class="tray__n">§ Ω</div>
          <h2 class="tray__h">Your <em>tray.</em></h2>
        </div>
        <span class="tray__count">{{ itemCount }} item{{ itemCount === 1 ? '' : 's' }}</span>
      </header>

      <!-- Cart list -->
      <ul v-if="!isEmpty" class="tray__list" aria-live="polite">
        <li v-if="props.pairing" class="tray__item tray__item--pairing">
          <span class="tray__q">1×</span>
          <div class="tray__nm">
            {{ props.pairing.title }}
            <span class="tray__nm-sub">Pairing · pay at the bar on collection</span>
          </div>
          <span class="tray__pr">{{ formatCurrency(props.pairingPrice) }}</span>
          <button
            type="button"
            class="tray__rm"
            :aria-label="`Remove the ${props.pairing.title} pairing`"
            @click="emit('removePairing')"
          >
            Remove
          </button>
        </li>

        <li v-for="f in props.foodItems" :key="f.itemId" class="tray__item">
          <span class="tray__q">{{ f.quantity }}×</span>
          <div class="tray__nm">{{ f.name }}</div>
          <span class="tray__pr">{{ formatCurrency(f.unitPrice * f.quantity) }}</span>
          <button
            type="button"
            class="tray__rm"
            :aria-label="`Remove ${f.name} from your tray`"
            @click="emit('removeItem', f.itemId)"
          >
            Remove
          </button>
        </li>
      </ul>

      <div v-else class="tray__empty">
        <b>Your tray is empty</b>
        Add something from the counter, or skip ahead to payment.
      </div>

      <!-- Totals -->
      <dl class="tray__totals">
        <div class="tray__line">
          <span>Concessions</span>
          <span class="tray__v">{{ formatCurrency(foodTotal) }}</span>
        </div>
        <div v-if="props.pairing" class="tray__line tray__line--pairing">
          <span>
            Programme pairing
            <small>pay at the bar</small>
          </span>
          <span class="tray__v">{{ formatCurrency(props.pairingPrice) }}</span>
        </div>
        <div v-if="props.pairingSavings > 0" class="tray__line tray__line--saving">
          <span>Bundle savings vs à la carte</span>
          <span class="tray__v tray__v--saving">−{{ formatCurrency(props.pairingSavings) }}</span>
        </div>
        <div class="tray__line">
          <span>{{ ticketsLabel }}</span>
          <span class="tray__v">{{ formatCurrency(seatsTotal) }}</span>
        </div>
        <div class="tray__grand">
          <span>
            Charged now
            <small>USD</small>
          </span>
          <span class="tray__grand-v">{{ formatCurrency(grandTotal) }}</span>
        </div>
      </dl>

      <button
        type="button"
        class="tray__pay"
        :disabled="props.seats.length === 0"
        @click="emit('continue')"
      >
        <span>Continue to payment</span>
        <span class="tray__pay-amt">{{ formatCurrency(grandTotal) }}</span>
      </button>

      <button type="button" class="tray__skip" @click="emit('skip')">
        Skip concessions
      </button>

      <p class="tray__note">
        Concessions are charged to the same card as your tickets.
        Pre-orders may be cancelled up to <b>30 minutes before showtime</b> — refunded to source, minus the pour.
      </p>
    </section>
  </div>
</template>

<style scoped>
.tray {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.tray__bay {
  background-color: var(--surface-container);
  border-radius: var(--radius-card);
  padding: var(--space-lg);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

/* Mini show summary */
.tray__show {
  display: flex;
  gap: var(--space-md);
  align-items: flex-start;
  padding-bottom: var(--space-sm);
  margin-bottom: var(--space-sm);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.tray__poster {
  width: 3.25rem;
  aspect-ratio: 2 / 3;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
  background: radial-gradient(ellipse at 30% 30%, #c47040, #6b2818 55%, #1f0d08 100%);
  position: relative;
  overflow: hidden;
}

.tray__poster::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.08) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
}

.tray__info {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--tertiary);
  line-height: 1.4;
  min-width: 0;
}

.tray__title {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--on-surface);
  letter-spacing: -0.005em;
  display: block;
  margin-bottom: 0.15rem;
}

.tray__sub {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--tertiary);
}

.tray__row {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: 0.25rem;
}

/* Header */
.tray__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-md);
  margin-bottom: var(--space-md);
  padding-bottom: var(--space-sm);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.tray__n {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
}

.tray__h {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.1;
  margin-top: 0.2rem;
  color: var(--on-surface);
}

.tray__h em {
  font-style: italic;
  color: var(--tertiary);
}

.tray__count {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

/* Cart list */
.tray__list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  max-height: 17.5rem;
  overflow-y: auto;
  margin: 0 calc(-1 * var(--space-sm));
  padding: 0 var(--space-sm);
}

.tray__list::-webkit-scrollbar {
  width: 0.25rem;
}

.tray__list::-webkit-scrollbar-thumb {
  background-color: rgb(var(--outline-variant-rgb) / 0.4);
  border-radius: var(--radius-sm);
}

.tray__item {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  gap: var(--space-sm);
  align-items: center;
  padding: 0.6rem 0;
  border-bottom: var(--border-hairline) dashed rgb(var(--outline-variant-rgb) / 0.25);
}

.tray__item:last-child {
  border-bottom: none;
}

.tray__q {
  font-family: var(--font-display);
  font-size: 0.875rem;
  color: var(--secondary);
  font-variant-numeric: tabular-nums;
  font-weight: 500;
  min-width: 1.5rem;
  padding: 0.2rem 0.35rem;
  background-color: rgb(var(--secondary-rgb) / 0.08);
  border-radius: var(--radius-sm);
  text-align: center;
}

.tray__nm {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--on-surface);
  letter-spacing: -0.005em;
  line-height: 1.3;
}

.tray__nm-sub {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  display: block;
  margin-top: 0.1rem;
}

.tray__pr {
  font-family: var(--font-display);
  font-size: 0.875rem;
  color: var(--tertiary);
  font-variant-numeric: tabular-nums;
}

.tray__rm {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  background: none;
  border: none;
  padding: 0.2rem 0;
  cursor: pointer;
  transition: color var(--duration-standard) var(--ease-standard);
}

.tray__rm:hover,
.tray__rm:focus-visible {
  color: var(--secondary);
}

/* Empty state */
.tray__empty {
  padding: var(--space-lg) 0;
  text-align: center;
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
}

.tray__empty b {
  display: block;
  font-family: var(--font-display);
  font-style: normal;
  font-size: 1rem;
  color: var(--tertiary);
  margin-bottom: 0.35rem;
  letter-spacing: -0.005em;
}

/* Totals */
.tray__totals {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  margin: var(--space-md) 0 0;
  padding-top: var(--space-md);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.3);
}

.tray__line {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--tertiary);
}

.tray__line--saving span {
  color: var(--secondary);
}

.tray__v {
  font-family: var(--font-display);
  color: var(--on-surface);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
}

.tray__v--saving {
  color: var(--secondary);
}

.tray__grand {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-sm);
  margin-top: 0.15rem;
  border-top: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.3);
  font-family: var(--font-body);
  font-size: 1rem;
  color: var(--on-surface);
}

.tray__grand small {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.12em;
  margin-left: 0.4rem;
}

.tray__grand-v {
  font-family: var(--font-display);
  font-size: 1.875rem;
  color: var(--secondary);
  letter-spacing: -0.02em;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

/* CTAs */
.tray__pay {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 3.25rem;
  padding: 0 1.25rem;
  border-radius: var(--radius-sm);
  background-color: var(--secondary);
  color: var(--surface);
  border: none;
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  width: 100%;
  margin-top: var(--space-md);
  cursor: pointer;
  box-shadow: var(--shadow-float);
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    transform var(--duration-micro) var(--ease-standard);
}

.tray__pay:hover:not(:disabled) {
  background-color: var(--secondary-hover);
}

.tray__pay:active:not(:disabled) {
  transform: scale(0.99);
}

.tray__pay:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

.tray__pay-amt {
  font-variant-numeric: tabular-nums;
}

.tray__skip {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  padding: 0.8rem;
  margin-top: var(--space-sm);
  width: 100%;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.5);
  border-radius: var(--radius-sm);
  background: none;
  color: var(--tertiary);
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  cursor: pointer;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.tray__skip:hover,
.tray__skip:focus-visible {
  border-color: var(--secondary);
  color: var(--secondary);
}

.tray__note {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  line-height: 1.55;
  color: var(--on-tertiary-fixed-variant);
  margin: var(--space-md) 0 0;
  padding-top: var(--space-md);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
  letter-spacing: 0.02em;
}

.tray__note b {
  color: var(--secondary);
  font-weight: 500;
}
</style>
