<script setup lang="ts">
import type { MenuItem } from '~/types/menu-item'

/**
 * Checkout food pre-order panel.
 *
 * Renders the cross-location menu (from GET /api/food-menu) in the context of
 * a specific booking location. Items whose `available_at` array excludes the
 * booking's location slug are visually dimmed (opacity 0.4), have their
 * quantity controls hidden, and receive a `CvBadge variant="warning"` overlay
 * reading "Not available at {locationName}".
 *
 * Cart-level guard: add/increment events are suppressed for unavailable items
 * at the component boundary. useCart.addFoodItem carries a second line of
 * defense — if a buggy caller bypasses the UI, the cart rejects the add and
 * shows a toast.
 *
 * FTC / availability contract: "isAvailableHere" defaults to true when
 * available_at is absent or empty. This handles the window between backend
 * deployment and the array being populated, keeping the panel interactive
 * rather than silently blocking all items.
 */

const props = defineProps<{
  /** Full cross-location menu items (from useFoodMenu().fetchAll()). */
  items: MenuItem[]
  /** itemId → quantity map from useCart. */
  cart: Record<string, number>
  /** Slug of the location this booking is at. */
  bookingLocationSlug: string
  /** Human-readable name of the booking location, used in the badge caption. */
  bookingLocationName: string
}>()

const emit = defineEmits<{
  add: [itemId: string]
  increment: [itemId: string]
  decrement: [itemId: string]
}>()

/**
 * Whether an item is available at the booking's location.
 * Defaults to true when available_at is absent, null, or empty — defensive
 * default while the backend populates the array.
 */
function isAvailableHere(item: MenuItem): boolean {
  const arr = item.available_at
  if (!arr || arr.length === 0) return true
  return arr.includes(props.bookingLocationSlug)
}

/**
 * Suppress add/increment events for unavailable items. The cart carries its
 * own guard (useCart.addFoodItem), but we never emit the event in the first
 * place so unavailable items can't be added through the normal UI path.
 */
function handleAdd(itemId: string) {
  const item = props.items.find(i => i.id === itemId)
  if (!item || !isAvailableHere(item)) return
  emit('add', itemId)
}

function handleIncrement(itemId: string) {
  const item = props.items.find(i => i.id === itemId)
  if (!item || !isAvailableHere(item)) return
  emit('increment', itemId)
}

function handleDecrement(itemId: string) {
  emit('decrement', itemId)
}

// Partition items: available at this location vs. not.
// Items with empty available_at are shown (defensive default).
const availableItems = computed<MenuItem[]>(() =>
  props.items.filter(item => isAvailableHere(item)),
)

const unavailableItems = computed<MenuItem[]>(() =>
  props.items.filter(item => !isAvailableHere(item)),
)
</script>

<template>
  <div class="fpp">
    <!-- Available items — pass-through to the catalog -->
    <ConcessionsCatalog
      v-if="availableItems.length > 0"
      :items="availableItems"
      :cart="cart"
      :interactive="true"
      @add="handleAdd"
      @increment="handleIncrement"
      @decrement="handleDecrement"
    />

    <!-- Unavailable items — dimmed section rendered below the catalog -->
    <section
      v-if="unavailableItems.length > 0"
      class="fpp__unavailable"
      aria-label="Items not available at this location"
    >
      <header class="fpp__unavailable-head">
        <span class="fpp__unavailable-label">Not available at {{ bookingLocationName }}</span>
      </header>

      <div class="fpp__unavailable-grid">
        <div
          v-for="item in unavailableItems"
          :key="item.id"
          class="fpp__dimmed-item"
          aria-disabled="true"
        >
          <!-- Dimmed item card — non-interactive echo of ConcessionItemCard structure -->
          <div class="fpp__dimmed-thumb" aria-hidden="true">
            <span class="fpp__dimmed-glyph">{{ item.name.charAt(0).toUpperCase() }}</span>
          </div>

          <div class="fpp__dimmed-body">
            <div class="fpp__dimmed-head">
              <span class="fpp__dimmed-name">{{ item.name }}</span>
              <span class="fpp__dimmed-price">{{ formatCurrency(item.price) }}</span>
            </div>
            <p class="fpp__dimmed-desc">{{ item.description }}</p>
          </div>

          <!-- Warning badge overlay -->
          <div class="fpp__unavail-badge" role="note" :aria-label="`Not available at ${bookingLocationName}`">
            <CvBadge variant="warning">Not available at {{ bookingLocationName }}</CvBadge>
          </div>
        </div>
      </div>
    </section>

    <!-- Empty state: no items at all -->
    <p v-if="items.length === 0" class="fpp__empty">
      No menu items available. Check back closer to your screening.
    </p>
  </div>
</template>

<style scoped>
.fpp {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

/* Unavailable items section */
.fpp__unavailable {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  opacity: 0.4;
  /* Pointer-events still on so screen readers can navigate; not clickable */
  pointer-events: none;
}

.fpp__unavailable-head {
  padding-bottom: var(--space-sm);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.fpp__unavailable-label {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--state-warning);
}

.fpp__unavailable-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-md);
}

/* Individual dimmed item card */
.fpp__dimmed-item {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
  border-radius: var(--radius-card);
  /* No hover lift — non-interactive */
}

.fpp__dimmed-thumb {
  aspect-ratio: 4 / 3;
  border-radius: var(--radius-sm);
  background-color: var(--surface-container);
  display: grid;
  place-items: center;
}

.fpp__dimmed-glyph {
  font-family: var(--font-display);
  font-style: italic;
  font-size: 3.5rem;
  color: rgb(var(--on-surface-rgb) / 0.1);
  letter-spacing: -0.05em;
}

.fpp__dimmed-body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.fpp__dimmed-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--space-sm);
}

.fpp__dimmed-name {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.15;
  color: var(--on-surface);
  text-wrap: pretty;
}

.fpp__dimmed-price {
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--tertiary);
  font-weight: 500;
  letter-spacing: -0.01em;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.fpp__dimmed-desc {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.5;
  font-style: italic;
  margin: 0;
}

/* Warning badge overlay — pointer-events: auto so the badge text is accessible */
.fpp__unavail-badge {
  pointer-events: auto;
  margin-top: auto;
  padding-top: 0.6rem;
}

.fpp__empty {
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  padding: var(--space-lg) 0;
  margin: 0;
}

@media (max-width: 68.75rem) {
  .fpp__unavailable-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 40rem) {
  .fpp__unavailable-grid {
    grid-template-columns: 1fr;
  }
}
</style>
