<script setup lang="ts">
import type { MenuItem } from '~/types/menu-item'

const props = defineProps<{
  menuItems: MenuItem[]
  selectedItems: Array<{ itemId: string; quantity: number }>
}>()

const emit = defineEmits<{
  update: [items: Array<{ itemId: string; quantity: number }>]
}>()

/** Deterministic gradient per item category — evokes the curated-menu look of the design. */
const CATEGORY_GRADIENTS: Record<string, string> = {
  popcorn: 'radial-gradient(ellipse at 50% 40%, #d4a868 0%, #8a5a2a 55%, #2a1808 100%)',
  drinks: 'radial-gradient(ellipse at 40% 45%, #8a2020 0%, #3a0a0a 55%, #180404 100%)',
  snacks: 'radial-gradient(ellipse at 50% 50%, #a07840 0%, #502810 55%, #180a04 100%)',
  combos: 'radial-gradient(ellipse at 40% 40%, #c88a30 0%, #6a4010 55%, #1a0a04 100%)',
  specials: 'radial-gradient(ellipse at 50% 40%, #6a4020 0%, #2a1808 55%, #0a0402 100%)',
}

const DEFAULT_GRADIENT = 'radial-gradient(ellipse at 50% 50%, #a07840 0%, #502810 55%, #180a04 100%)'

function gradientFor(item: MenuItem): string {
  return CATEGORY_GRADIENTS[item.category] ?? DEFAULT_GRADIENT
}

function glyphFor(item: MenuItem): string {
  return item.name.trim().charAt(0).toUpperCase() || '·'
}

const availableItems = computed<MenuItem[]>(() =>
  props.menuItems.filter(i => i.available),
)

function getQuantity(itemId: string): number {
  return props.selectedItems.find(i => i.itemId === itemId)?.quantity ?? 0
}

function addItem(itemId: string) {
  const existing = props.selectedItems.find(i => i.itemId === itemId)
  if (existing) {
    emit('update', props.selectedItems.map(i =>
      i.itemId === itemId ? { ...i, quantity: i.quantity + 1 } : i,
    ))
  } else {
    emit('update', [...props.selectedItems, { itemId, quantity: 1 }])
  }
}

function removeItem(itemId: string) {
  const existing = props.selectedItems.find(i => i.itemId === itemId)
  if (!existing) return
  if (existing.quantity <= 1) {
    emit('update', props.selectedItems.filter(i => i.itemId !== itemId))
  } else {
    emit('update', props.selectedItems.map(i =>
      i.itemId === itemId ? { ...i, quantity: i.quantity - 1 } : i,
    ))
  }
}
</script>

<template>
  <section class="bay">
    <header class="bay__header">
      <div>
        <div class="bay__number">§ 03</div>
        <h2 class="bay__title">Add <em>concessions.</em></h2>
        <p class="bay__sub">Pre-order now — collect at the bar with your seat reference.</p>
      </div>
      <span class="bay__badge">Skip · Add later</span>
    </header>

    <p v-if="availableItems.length === 0" class="concessions__empty">
      The concessions menu is not available for this location yet.
    </p>

    <div v-else class="concessions__grid">
      <article
        v-for="item in availableItems"
        :key="item.id"
        class="conc"
        :class="{ 'conc--on': getQuantity(item.id) > 0 }"
      >
        <div
          class="conc__thumb"
          :style="{ background: gradientFor(item) }"
          aria-hidden="true"
        >
          <span class="conc__ic">{{ glyphFor(item) }}</span>
        </div>
        <h3 class="conc__name">{{ item.name }}</h3>
        <p class="conc__dx">{{ item.description }}</p>
        <div class="conc__foot">
          <span class="conc__pr">{{ formatCurrency(item.price) }}</span>
          <div class="conc__qty">
            <button
              type="button"
              class="conc__qty-btn"
              :aria-label="`Remove one ${item.name}`"
              :disabled="getQuantity(item.id) === 0"
              @click="removeItem(item.id)"
            >
              −
            </button>
            <span class="conc__qty-n" aria-live="polite">{{ getQuantity(item.id) }}</span>
            <button
              type="button"
              class="conc__qty-btn"
              :aria-label="`Add one ${item.name}`"
              @click="addItem(item.id)"
            >
              +
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.concessions__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-sm);
}

.concessions__empty {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--on-tertiary-fixed-variant);
  padding: var(--space-md) 0;
  margin: 0;
}

.conc {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: var(--space-md);
  border: 0.0625rem solid rgba(87, 66, 62, 0.25);
  border-radius: 0.125rem;
  background-color: var(--surface-container-low);
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard);
  text-align: left;
  position: relative;
}

.conc:hover {
  border-color: rgba(218, 199, 105, 0.4);
}

.conc--on {
  border-color: var(--secondary);
  background-color: rgba(218, 199, 105, 0.06);
}

.conc__thumb {
  aspect-ratio: 4 / 3;
  border-radius: 0.125rem;
  position: relative;
  overflow: hidden;
  margin-bottom: 0.2rem;
}

.conc__thumb::before {
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

.conc__ic {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 3rem;
  color: rgba(255, 255, 255, 0.14);
  letter-spacing: -0.05em;
}

.conc__name {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  letter-spacing: -0.005em;
  line-height: 1.2;
  color: var(--on-surface);
  margin: 0;
}

.conc__dx {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--tertiary);
  line-height: 1.35;
  min-height: 2.7em;
  margin: 0;
}

.conc__foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 0.4rem;
  border-top: 0.0625rem solid rgba(87, 66, 62, 0.2);
  margin-top: 0.2rem;
}

.conc__pr {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: -0.01em;
  font-variant-numeric: tabular-nums;
}

.conc__qty {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
  font-size: 0.9375rem;
  color: var(--on-surface);
}

.conc__qty-btn {
  width: 1.5rem;
  height: 1.5rem;
  border: 0.0625rem solid rgba(87, 66, 62, 0.4);
  border-radius: 0.125rem;
  display: grid;
  place-items: center;
  color: var(--on-surface);
  background: none;
  cursor: pointer;
  font-family: var(--font-display);
  font-size: 1rem;
  line-height: 1;
  transition:
    border-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard);
}

.conc__qty-btn:hover:not(:disabled),
.conc__qty-btn:focus-visible {
  border-color: var(--secondary);
  color: var(--secondary);
}

.conc__qty-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.conc__qty-n {
  min-width: 1.2rem;
  text-align: center;
}

@media (max-width: 68.75rem) {
  .concessions__grid {
    grid-template-columns: 1fr 1fr;
  }

  .conc__qty-btn {
    width: 3rem;
    height: 3rem;
  }
}

@media (max-width: 40rem) {
  .concessions__grid {
    grid-template-columns: 1fr;
  }
}
</style>
