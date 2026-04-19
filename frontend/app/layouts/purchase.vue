<script setup lang="ts">
const { currentStep, completedSteps, navigableSteps } = usePurchaseStep()
const cart = useCart()

// Cart items derived from global cart state
const cartItems = computed(() => {
  const items: Array<{ label: string; price: number }> = []

  for (const seat of cart.seats.value) {
    items.push({
      label: `Seat ${seat.seatId} (${seat.section})`,
      price: seat.price,
    })
  }

  for (const food of cart.foodItems.value) {
    items.push({
      label: `${food.name} x${food.quantity}`,
      price: food.unitPrice * food.quantity,
    })
  }

  if (cart.promoDiscount.value > 0) {
    items.push({
      label: `Promo: ${cart.promoCode.value}`,
      price: -cart.promoDiscount.value,
    })
  }

  if (cart.giftCardAmount.value > 0) {
    items.push({
      label: 'Gift Card',
      price: -cart.giftCardAmount.value,
    })
  }

  return items
})

const hasCartItems = computed(() => cart.seats.value.length > 0)

function handleStepNavigate(step: number) {
  if (step === 1 && cart.showtime.value) {
    navigateTo(`/purchase/${cart.showtime.value.id}`)
  }
}
</script>

<template>
  <div class="layout-purchase">
    <SkipNav />
    <header class="layout-purchase__header" role="banner">
      <div class="layout-purchase__header-inner">
        <NuxtLink to="/" class="layout-purchase__logo">
          Final Cut
        </NuxtLink>

        <div class="layout-purchase__steps">
          <PurchaseStepIndicator
            :current-step="currentStep"
            :completed-steps="completedSteps"
            :navigable-steps="navigableSteps"
            @navigate="handleStepNavigate"
          />
        </div>

        <div class="layout-purchase__header-extras">
          <slot name="header-extras" />
        </div>
      </div>
    </header>

    <slot name="below-header" />

    <div class="layout-purchase__body">
      <main id="main-content" tabindex="-1" class="layout-purchase__main">
        <slot />
      </main>

      <aside
        v-if="$slots.rail || hasCartItems"
        class="layout-purchase__cart"
        aria-label="Order summary"
      >
        <slot name="rail">
          <CartSummary
            :items="cartItems"
            :total="cart.total.value"
          />
        </slot>
      </aside>
    </div>

    <CvToastContainer />
  </div>
</template>

<style scoped>
.layout-purchase {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
}

/* Header */
.layout-purchase__header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 4rem;
  background-color: var(--surface-container);
  z-index: var(--z-sticky);
}

.layout-purchase__header-inner {
  display: flex;
  align-items: center;
  height: 100%;
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-md);
  gap: var(--space-lg);
}

@media (min-width: 40rem) {
  .layout-purchase__header-inner {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .layout-purchase__header-inner {
    padding-inline: var(--space-2xl);
  }
}

.layout-purchase__logo {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  text-decoration: none;
  flex-shrink: 0;
}

.layout-purchase__steps {
  flex: 1;
  display: flex;
  justify-content: center;
}

.layout-purchase__header-extras {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-md);
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
}

/* Body */
.layout-purchase__body {
  display: flex;
  flex: 1;
  max-width: 90rem;
  width: 100%;
  margin-inline: auto;
  padding-inline: var(--space-md);
  padding-top: 4rem;
  gap: var(--space-2xl);
}

@media (min-width: 40rem) {
  .layout-purchase__body {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .layout-purchase__body {
    padding-inline: var(--space-2xl);
  }
}

.layout-purchase__main {
  flex: 1;
  min-width: 0;
  padding-top: var(--space-lg);
  padding-bottom: var(--space-xl);
}

.layout-purchase__main:focus {
  outline: none;
}

/* Cart sidebar (desktop) — width is controlled by slotted content so that
   pages can render a wider rail (e.g. checkout's 25rem totals panel). */
.layout-purchase__cart {
  display: none;
  flex-shrink: 0;
  padding-top: var(--space-lg);
}

@media (min-width: 60rem) {
  .layout-purchase__cart {
    display: block;
    width: 20rem;
  }
}

@media (min-width: 68.75rem) {
  .layout-purchase__cart {
    width: 25rem;
  }
}
</style>
