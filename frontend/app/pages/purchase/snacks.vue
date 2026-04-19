<script setup lang="ts">
import { getPairingForMovie } from '~/data/pairings'

definePageMeta({
  layout: false,
})

useHead({
  title: 'Snacks & Bar — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const cart = useCart()
const { activeLocation } = useLocations()
const { items, fetchMenu } = useFoodMenu()
const { show: showToast } = useToast()
const { setStep } = usePurchaseStep()

setStep(2, [1], [1])

// Guard: redirect if no seats in cart
onMounted(async () => {
  if (cart.seats.value.length === 0) {
    showToast({ message: 'No seats selected. Please select seats first.', type: 'error' })
    await navigateTo('/')
    return
  }
  await fetchMenu()
})

// Re-fetch when the active location swaps (e.g., user changes locations mid-session).
watch(activeLocation, () => {
  fetchMenu()
})

// Pairing for the current film, if curated.
const pairing = computed(() => getPairingForMovie(cart.showtime.value?.movieSlug))
const pairingAdded = computed<boolean>(() => cart.pairing.value?.id === pairing.value?.id && !!cart.pairing.value)

// Cart shape for the catalog: { itemId: quantity }
const cartMap = computed<Record<string, number>>(() => {
  const map: Record<string, number> = {}
  for (const item of cart.foodItems.value) {
    map[item.itemId] = item.quantity
  }
  return map
})

const changeSeatsHref = computed<string | null>(() =>
  cart.showtime.value ? `/purchase/${cart.showtime.value.id}` : null,
)

function handleAdd(itemId: string) {
  const menuItem = items.value.find(m => m.id === itemId)
  if (!menuItem) return
  cart.addFoodItem(menuItem.id, menuItem.name, menuItem.price)
}

function handleIncrement(itemId: string) {
  const menuItem = items.value.find(m => m.id === itemId)
  if (!menuItem) return
  cart.addFoodItem(menuItem.id, menuItem.name, menuItem.price)
}

function handleDecrement(itemId: string) {
  cart.removeFoodItem(itemId)
}

function handleRemoveItem(itemId: string) {
  // Drop the item entirely (set quantity to 0).
  const existing = cart.foodItems.value.find(f => f.itemId === itemId)
  if (!existing) return
  for (let i = 0; i < existing.quantity; i++) {
    cart.removeFoodItem(itemId)
  }
}

function handlePairingToggle() {
  if (!pairing.value) return
  if (pairingAdded.value) {
    cart.clearPairing()
  } else {
    cart.setPairing(pairing.value)
  }
}

function handleRemovePairing() {
  cart.clearPairing()
}

function handleContinue() {
  navigateTo('/purchase/checkout')
}

function handleSkip() {
  // Drop any pairing on skip — user opted out of the bundle.
  cart.clearPairing()
  navigateTo('/purchase/checkout')
}

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
</script>

<template>
  <NuxtLayout name="purchase">
    <!-- Hold strip (same shell as the seat selection + checkout pages) -->
    <template #below-header>
      <CheckoutHoldTimer
        v-if="cart.showtime.value && cart.seats.value.length > 0"
        :time-remaining="cart.timeRemaining.value"
        :auditorium="cart.showtime.value.screenName"
        :seats="cart.seats.value"
        :change-seats-href="changeSeatsHref"
        @release="handleRelease"
      />
    </template>

    <template #header-extras>
      <div v-if="activeLocation" class="snacks-loc">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" /></svg>
        <b>{{ activeLocation.name }}</b>
      </div>
    </template>

    <template #rail>
      <ConcessionsTrayRail
        :showtime="cart.showtime.value"
        :seats="cart.seats.value"
        :food-items="cart.foodItems.value"
        :pairing="cart.pairing.value"
        :pairing-price="cart.pairingPrice.value"
        :pairing-savings="cart.pairingSavings.value"
        @remove-pairing="handleRemovePairing"
        @remove-item="handleRemoveItem"
        @continue="handleContinue"
        @skip="handleSkip"
      />
    </template>

    <div class="snacks-page" :class="{ 'snacks-page--with-hold': cart.seats.value.length > 0 }">
      <header class="snacks-page__top">
        <div>
          <p class="snacks-page__eyebrow">Reel 02 · Concessions</p>
          <h1 class="snacks-page__title">
            <em>The bar &amp;</em> snack counter.
          </h1>
          <p class="snacks-page__lede">
            Curated by the programming team — small selection, better quality.
            Pre-order now and pick up at the lobby bar before the film.
          </p>
        </div>
        <div v-if="cart.showtime.value" class="snacks-page__meta">
          <b>{{ formatDateTime(cart.showtime.value.startTime) }}</b>
          {{ cart.showtime.value.movieTitle }} · {{ cart.showtime.value.screenName }}
        </div>
      </header>

      <ProgrammePairingCard
        v-if="pairing"
        :pairing="pairing"
        :added="pairingAdded"
        @toggle="handlePairingToggle"
      />

      <ConcessionsCatalog
        :items="items"
        :cart="cartMap"
        :interactive="true"
        @add="handleAdd"
        @increment="handleIncrement"
        @decrement="handleDecrement"
      />

      <ConcessionsAllergenNotice />

      <ConcessionsCollectionInfo />
    </div>
  </NuxtLayout>
</template>

<style scoped>
.snacks-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
  padding-top: var(--space-lg);
}

.snacks-page--with-hold {
  padding-top: 3rem; /* compensate for the 2.5rem hold strip */
}

.snacks-page__top {
  padding: var(--space-xl) 0 var(--space-lg);
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-lg);
  flex-wrap: wrap;
  border-bottom: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2);
}

.snacks-page__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0 0 0.5rem;
}

.snacks-page__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.snacks-page__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.25rem);
  line-height: 1;
  letter-spacing: -0.03em;
  text-wrap: balance;
  max-width: 18ch;
  color: var(--on-surface);
  margin: 0;
}

.snacks-page__title em {
  font-style: italic;
  color: var(--tertiary);
}

.snacks-page__lede {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1rem;
  max-width: 52ch;
  margin-top: var(--space-md);
  margin-bottom: 0;
  line-height: 1.55;
  font-style: italic;
}

.snacks-page__meta {
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
}

.snacks-page__meta b {
  font-family: var(--font-display);
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.04em;
  font-size: 0.9375rem;
  text-transform: none;
  display: block;
  margin-bottom: 0.2rem;
}

.snacks-loc {
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

.snacks-loc svg {
  width: 0.875rem;
  height: 0.875rem;
}

.snacks-loc b {
  color: var(--on-surface);
  font-weight: 500;
}

@media (max-width: 40rem) {
  .snacks-loc {
    display: none;
  }
}
</style>
