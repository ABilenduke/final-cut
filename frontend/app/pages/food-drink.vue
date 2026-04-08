<script setup lang="ts">
import { menuData } from '~/data/menu'

const route = useRoute()
const router = useRouter()

// Derive categories from menu data
const categories = computed(() => {
  const cats = [...new Set(menuData.map(item => item.category))]
  return cats
})

const activeCategory = computed(() => {
  const cat = route.query.category as string
  return cat && categories.value.includes(cat) ? cat : categories.value[0]
})

const filteredItems = computed(() => {
  return menuData.filter(item => item.category === activeCategory.value && item.available)
})

function selectCategory(category: string) {
  router.replace({ query: { ...route.query, category } })
}

useHead({
  title: 'Food & Drink — Final Cut',
  meta: [
    { name: 'description', content: 'Explore our menu of popcorn, drinks, snacks, combos, and specials at Final Cut.' },
  ],
})
</script>

<template>
  <div class="food-drink-page">
    <div class="container">
      <h1 class="food-drink-page__title display-sm">Food & Drink</h1>
      <MenuCategoryTabs
        :categories="categories"
        :active="activeCategory"
        @select="selectCategory"
      />
      <div class="ensemble">
        <MenuItemCard
          v-for="item in filteredItems"
          :key="item.id"
          :item="item"
        />
      </div>
      <p v-if="filteredItems.length === 0" class="food-drink-page__empty body-lg">
        No items available in this category.
      </p>
    </div>
  </div>
</template>

<style scoped>
.food-drink-page {
  padding: var(--space-3xl) 0;
}

.food-drink-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-lg);
}

.ensemble {
  margin-top: var(--space-lg);
}

.food-drink-page__empty {
  color: var(--tertiary);
  text-align: center;
  padding: var(--space-3xl) 0;
}
</style>
