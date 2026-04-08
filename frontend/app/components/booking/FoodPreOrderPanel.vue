<script setup lang="ts">
import type { MenuItem } from '~/types/menu-item'

const props = defineProps<{
  menuItems: MenuItem[]
  selectedItems: Array<{ itemId: string; quantity: number }>
}>()

const emit = defineEmits<{
  update: [items: Array<{ itemId: string; quantity: number }>]
}>()

const isExpanded = ref(false)
const activeCategory = ref<string>('combos')

const categories = computed(() => {
  const cats = new Set(props.menuItems.map(i => i.category))
  return [...cats]
})

const categoryLabels: Record<string, string> = {
  popcorn: 'Popcorn',
  drinks: 'Drinks',
  snacks: 'Snacks',
  combos: 'Combos',
  specials: 'Specials',
}

const filteredItems = computed(() =>
  props.menuItems.filter(i => i.category === activeCategory.value && i.available),
)

// Show top combos in collapsed view
const popularItems = computed(() =>
  props.menuItems.filter(i => i.category === 'combos' && i.available).slice(0, 3),
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
  <div class="food-panel">
    <!-- Collapsed state -->
    <div
      v-if="!isExpanded"
      class="food-panel__teaser"
    >
      <div class="food-panel__teaser-content">
        <h3 class="food-panel__teaser-title">Add snacks to your order?</h3>
        <div class="food-panel__teaser-items">
          <div
            v-for="item in popularItems"
            :key="item.id"
            class="food-panel__teaser-item"
          >
            <span class="food-panel__teaser-name">{{ item.name }}</span>
            <span class="food-panel__teaser-price">{{ formatCurrency(item.price) }}</span>
          </div>
        </div>
      </div>
      <CvButton
        variant="secondary"
        @click="isExpanded = true"
      >
        Browse Menu
      </CvButton>
    </div>

    <!-- Expanded state -->
    <div v-else class="food-panel__expanded">
      <div class="food-panel__header">
        <h3 class="food-panel__title">Food & Drink</h3>
        <button
          type="button"
          class="food-panel__collapse"
          aria-label="Close menu"
          @click="isExpanded = false"
        >
          <CvIcon name="close" size="sm" />
        </button>
      </div>

      <!-- Category tabs -->
      <div class="food-panel__tabs" role="tablist" aria-label="Menu categories">
        <button
          v-for="cat in categories"
          :key="cat"
          :id="`food-tab-${cat}`"
          role="tab"
          :aria-selected="activeCategory === cat"
          :aria-controls="`food-panel-${cat}`"
          class="food-panel__tab"
          :class="{ 'food-panel__tab--active': activeCategory === cat }"
          @click="activeCategory = cat"
        >
          {{ categoryLabels[cat] ?? cat }}
        </button>
      </div>

      <!-- Item grid -->
      <div
        :id="`food-panel-${activeCategory}`"
        class="food-panel__grid"
        role="tabpanel"
        :aria-labelledby="`food-tab-${activeCategory}`"
      >
        <div
          v-for="item in filteredItems"
          :key="item.id"
          class="food-panel__item"
        >
          <div class="food-panel__item-info">
            <span class="food-panel__item-name">{{ item.name }}</span>
            <span class="food-panel__item-price">{{ formatCurrency(item.price) }}</span>
          </div>
          <p class="food-panel__item-desc">{{ item.description }}</p>
          <div class="food-panel__item-actions">
            <template v-if="getQuantity(item.id) > 0">
              <button
                class="food-panel__qty-btn"
                aria-label="Remove one"
                @click="removeItem(item.id)"
              >
                <CvIcon name="minus" size="sm" />
              </button>
              <span class="food-panel__qty">{{ getQuantity(item.id) }}</span>
              <button
                class="food-panel__qty-btn"
                aria-label="Add one more"
                @click="addItem(item.id)"
              >
                <CvIcon name="plus" size="sm" />
              </button>
            </template>
            <CvButton
              v-else
              variant="secondary"
              size="sm"
              @click="addItem(item.id)"
            >
              Add
            </CvButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Collapsed teaser */
.food-panel__teaser {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
  padding: var(--space-md);
  background-color: var(--surface-container-high);
  border-radius: 0.125rem;
}

.food-panel__teaser-title {
  font-family: var(--font-display);
  font-size: var(--type-title-md);
  color: var(--on-surface);
  margin-bottom: var(--space-sm);
}

.food-panel__teaser-items {
  display: flex;
  gap: var(--space-lg);
}

.food-panel__teaser-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
}

.food-panel__teaser-name {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
}

.food-panel__teaser-price {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--secondary);
}

@media (max-width: 59.999rem) {
  .food-panel__teaser {
    flex-direction: column;
    align-items: stretch;
  }

  .food-panel__teaser-items {
    flex-direction: column;
    gap: var(--space-sm);
  }

  .food-panel__teaser-item {
    flex-direction: row;
    justify-content: space-between;
  }
}

/* Expanded panel */
.food-panel__expanded {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.food-panel__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.food-panel__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--on-surface);
}

.food-panel__collapse {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--tertiary);
  padding: var(--space-xs);
}

/* Category tabs */
.food-panel__tabs {
  display: flex;
  gap: var(--space-xs);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.food-panel__tab {
  background: none;
  border: none;
  padding: var(--space-sm) var(--space-md);
  font-family: var(--font-body);
  font-size: var(--type-label-lg);
  color: var(--tertiary);
  cursor: pointer;
  white-space: nowrap;
  border-bottom: 0.125rem solid transparent;
  transition: color var(--duration-micro) var(--ease-standard),
    border-color var(--duration-micro) var(--ease-standard);
}

.food-panel__tab--active {
  color: var(--secondary);
  border-bottom-color: var(--secondary);
}

/* Item grid */
.food-panel__grid {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.food-panel__item {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
}

.food-panel__item-info {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.food-panel__item-name {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
}

.food-panel__item-price {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--secondary);
}

.food-panel__item-desc {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
  margin: 0;
}

.food-panel__item-actions {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  align-self: flex-end;
}

.food-panel__qty-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  background-color: var(--surface-container-high);
  border: none;
  border-radius: 0.125rem;
  color: var(--on-surface);
  cursor: pointer;
}

@media (max-width: 59.999rem) {
  .food-panel__qty-btn {
    width: 3rem;
    height: 3rem;
  }
}

.food-panel__qty {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
  min-width: 1.5rem;
  text-align: center;
}
</style>
