<script setup lang="ts">
import type { MenuItem } from '~/types/menu-item'

type ViewMode = 'grid' | 'list'
type CatalogCategory = 'all' | 'popcorn' | 'drinks' | 'snacks' | 'combos' | 'specials' | 'pairings'

const props = withDefaults(defineProps<{
  items: MenuItem[]
  /** itemId → quantity. Empty in browse-only mode. */
  cart?: Record<string, number>
  /** When false, hides Add / qty controls and view toggle. */
  interactive?: boolean
  defaultCategory?: CatalogCategory
  defaultView?: ViewMode
}>(), {
  cart: () => ({}),
  interactive: true,
  defaultCategory: 'all',
  defaultView: 'grid',
})

const emit = defineEmits<{
  add: [itemId: string]
  increment: [itemId: string]
  decrement: [itemId: string]
}>()

const activeCategory = ref<CatalogCategory>(props.defaultCategory)
const view = ref<ViewMode>(props.defaultView)

const CATEGORIES: Array<{ id: CatalogCategory; label: string }> = [
  { id: 'all', label: 'All' },
  { id: 'popcorn', label: 'Popcorn & snacks' },
  { id: 'drinks', label: 'Bar' },
  { id: 'snacks', label: 'Sweets' },
  { id: 'specials', label: 'Sweets & specials' },
  { id: 'combos', label: 'Combos' },
  { id: 'pairings', label: 'Pairings' },
]

// Section header copy keyed by category — mirrors the design's titles[] map.
const TITLES: Record<CatalogCategory, [string, string]> = {
  all: ['Everything', 'on the counter.'],
  popcorn: ['Popcorn', '& the dry counter.'],
  drinks: ['The', 'bar.'],
  snacks: ['Snacks for', 'the auditorium.'],
  combos: ['House', 'combos.'],
  specials: ['Sweets for', 'the intermission.'],
  pairings: ["Tonight's", 'pairings.'],
}

function countFor(catId: CatalogCategory): number {
  if (catId === 'all') return props.items.length
  if (catId === 'pairings') return props.items.filter(i => i.flag).length
  return props.items.filter(i => i.category === catId).length
}

function categoryHasItems(catId: CatalogCategory): boolean {
  return countFor(catId) > 0
}

const visibleCategories = computed(() =>
  // Drop chips that would render an empty filter — except "All", which is the default.
  CATEGORIES.filter(c => c.id === 'all' || categoryHasItems(c.id)),
)

const activeItems = computed<MenuItem[]>(() => {
  if (activeCategory.value === 'all') return props.items
  if (activeCategory.value === 'pairings') return props.items.filter(i => i.flag)
  return props.items.filter(i => i.category === activeCategory.value)
})

const sectionIndex = computed<string>(() => {
  const idx = visibleCategories.value.findIndex(c => c.id === activeCategory.value)
  return String(Math.max(0, idx) + 1).padStart(2, '0')
})

const sectionCategoryLabel = computed<string>(() => {
  const cat = visibleCategories.value.find(c => c.id === activeCategory.value)
  return cat?.label ?? 'All concessions'
})

const sectionTitle = computed<[string, string]>(() => TITLES[activeCategory.value])

function selectCategory(id: CatalogCategory) {
  activeCategory.value = id
}

function setView(v: ViewMode) {
  view.value = v
}

function quantityFor(itemId: string): number {
  return props.cart[itemId] ?? 0
}
</script>

<template>
  <div class="catalog">
    <!-- Filter bar: chips + view toggle -->
    <div class="catalog__filter">
      <span class="catalog__filter-label">Browse</span>
      <div class="catalog__chips" role="tablist" aria-label="Filter concessions by category">
        <button
          v-for="c in visibleCategories"
          :key="c.id"
          type="button"
          class="catalog__chip"
          :class="{ 'catalog__chip--on': activeCategory === c.id }"
          role="tab"
          :aria-selected="activeCategory === c.id"
          @click="selectCategory(c.id)"
        >
          <span>{{ c.label }}</span>
          <span class="catalog__chip-count">{{ String(countFor(c.id)).padStart(2, '0') }}</span>
        </button>
      </div>

      <div v-if="props.interactive" class="catalog__view" role="group" aria-label="View mode">
        <button
          type="button"
          class="catalog__view-btn"
          :class="{ 'catalog__view-btn--on': view === 'grid' }"
          :aria-pressed="view === 'grid'"
          @click="setView('grid')"
        >
          Grid
        </button>
        <button
          type="button"
          class="catalog__view-btn"
          :class="{ 'catalog__view-btn--on': view === 'list' }"
          :aria-pressed="view === 'list'"
          @click="setView('list')"
        >
          List
        </button>
      </div>
    </div>

    <!-- Section header with film-reel numbering -->
    <header class="catalog__sec-head">
      <div>
        <span class="catalog__sec-n">§ {{ sectionIndex }} · {{ sectionCategoryLabel }}</span>
        <h3 class="catalog__sec-title">
          <em>{{ sectionTitle[0] }}</em> {{ sectionTitle[1] }}
        </h3>
      </div>
      <p class="catalog__sec-info">
        <span>Updated <b>daily</b></span>
        <span aria-hidden="true"> · </span>
        <span>{{ activeItems.length }} item{{ activeItems.length === 1 ? '' : 's' }}</span>
      </p>
    </header>

    <!-- Product grid / list -->
    <div
      class="catalog__products"
      :class="{ 'catalog__products--list': view === 'list' }"
      :aria-busy="false"
    >
      <ConcessionItemCard
        v-for="item in activeItems"
        :key="item.id"
        :item="item"
        :quantity="quantityFor(item.id)"
        :interactive="props.interactive"
        @add="(id: string) => emit('add', id)"
        @increment="(id: string) => emit('increment', id)"
        @decrement="(id: string) => emit('decrement', id)"
      />
    </div>

    <p v-if="activeItems.length === 0" class="catalog__empty">
      Nothing in this section right now. Try another tab on the counter.
    </p>
  </div>
</template>

<style scoped>
.catalog {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

/* Filter bar */
.catalog__filter {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  flex-wrap: wrap;
  padding: var(--space-md) 0;
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.catalog__filter-label {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding-right: var(--space-sm);
  border-right: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
}

.catalog__chips {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.catalog__chip {
  padding: 0.5rem 0.9rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.45);
  border-radius: 999px; /* token-exception: editorial pill — see plan */
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--tertiary);
  background-color: var(--surface-container-low);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard);
}

.catalog__chip:hover {
  border-color: rgb(var(--secondary-rgb) / 0.4);
  color: var(--on-surface);
}

.catalog__chip--on {
  border-color: var(--secondary);
  color: var(--secondary);
  background-color: rgb(var(--secondary-rgb) / 0.06);
}

.catalog__chip-count {
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
  font-size: 0.6875rem;
  color: var(--on-tertiary-fixed-variant);
  padding-left: 0.35rem;
  border-left: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
}

.catalog__chip--on .catalog__chip-count {
  color: var(--secondary);
  border-left-color: rgb(var(--secondary-rgb) / 0.35);
}

.catalog__view {
  margin-left: auto;
  display: flex;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.45);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.catalog__view-btn {
  padding: 0.45rem 0.7rem;
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--tertiary);
  background: none;
  border: none;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.catalog__view-btn + .catalog__view-btn {
  border-left: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.45);
}

.catalog__view-btn--on {
  background-color: var(--secondary);
  color: var(--surface);
}

/* Section header */
.catalog__sec-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-md);
  padding-top: var(--space-md);
  padding-bottom: var(--space-md);
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.catalog__sec-n {
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
  display: block;
  margin-bottom: 0.35rem;
}

.catalog__sec-title {
  font-family: var(--font-display);
  font-size: 1.625rem;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.1;
  margin: 0;
  color: var(--on-surface);
}

.catalog__sec-title em {
  font-style: italic;
  color: var(--tertiary);
}

.catalog__sec-info {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin: 0;
  text-align: right;
}

.catalog__sec-info b {
  color: var(--tertiary);
  font-family: var(--font-display);
  letter-spacing: 0.04em;
}

/* Product grid */
.catalog__products {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-md);
}

.catalog__products--list {
  grid-template-columns: 1fr;
}

.catalog__empty {
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  padding: var(--space-lg) 0;
  margin: 0;
}

@media (max-width: 68.75rem) {
  .catalog__products {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 40rem) {
  .catalog__products {
    grid-template-columns: 1fr;
  }
}
</style>
