<script setup lang="ts">
const props = defineProps<{
  items: Array<{ label: string; price: number }>
  total: number
}>()

const isExpanded = ref(false)
</script>

<template>
  <div class="cart-summary">
    <!-- Desktop: always visible -->
    <div class="cart-summary__desktop">
      <h3 class="cart-summary__heading">Order Summary</h3>
      <ul class="cart-summary__list">
        <li
          v-for="(item, index) in items"
          :key="`${item.label}-${item.price}`"
          class="cart-summary__item"
        >
          <span class="cart-summary__item-label">{{ item.label }}</span>
          <span class="cart-summary__item-price">{{ formatCurrency(item.price) }}</span>
        </li>
      </ul>
      <div v-if="items.length === 0" class="cart-summary__empty">
        No items selected
      </div>
      <div class="cart-summary__total" aria-live="polite">
        <span class="cart-summary__total-label">Total</span>
        <span class="cart-summary__total-price">{{ formatCurrency(total) }}</span>
      </div>
    </div>

    <!-- Mobile: collapsible bottom sheet -->
    <div class="cart-summary__mobile">
      <button
        class="cart-summary__toggle"
        :aria-expanded="isExpanded"
        @click="isExpanded = !isExpanded"
      >
        <span class="cart-summary__toggle-label">
          Total: {{ formatCurrency(total) }}
        </span>
        <CvIcon :name="isExpanded ? 'chevron-down' : 'chevron-up'" size="sm" />
      </button>
      <div v-if="isExpanded" class="cart-summary__sheet">
        <ul class="cart-summary__list">
          <li
            v-for="(item, index) in items"
            :key="`${item.label}-${item.price}`"
            class="cart-summary__item"
          >
            <span class="cart-summary__item-label">{{ item.label }}</span>
            <span class="cart-summary__item-price">{{ formatCurrency(item.price) }}</span>
          </li>
        </ul>
        <div v-if="items.length === 0" class="cart-summary__empty">
          No items selected
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Desktop version */
.cart-summary__desktop {
  display: none;
}

@media (min-width: 60rem) {
  .cart-summary__desktop {
    display: block;
    position: sticky;
    top: 5rem;
  }

  .cart-summary__mobile {
    display: none;
  }
}

.cart-summary__heading {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--on-surface);
  margin-bottom: var(--space-md);
}

.cart-summary__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.cart-summary__item {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.cart-summary__item-label {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
}

.cart-summary__item-price {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--on-surface);
}

.cart-summary__empty {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--on-tertiary-fixed-variant);
  padding: var(--space-sm) 0;
}

.cart-summary__total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-top: var(--space-md);
  padding-top: var(--space-md);
  border-top: 0.0625rem solid var(--outline-variant);
}

.cart-summary__total-label {
  font-family: var(--font-display);
  font-size: var(--type-title-lg);
  color: var(--on-surface);
}

.cart-summary__total-price {
  font-family: var(--font-display);
  font-size: var(--type-title-lg);
  color: var(--secondary);
}

/* Mobile bottom sheet */
.cart-summary__mobile {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: var(--surface-container);
  z-index: var(--z-sticky);
}

.cart-summary__toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  height: 3.5rem;
  padding-inline: var(--space-md);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--on-surface);
}

.cart-summary__toggle-label {
  font-family: var(--font-display);
  font-size: var(--type-title-md);
  color: var(--secondary);
}

.cart-summary__sheet {
  padding: 0 var(--space-md) var(--space-md);
  max-height: 50dvh;
  overflow-y: auto;
}
</style>
