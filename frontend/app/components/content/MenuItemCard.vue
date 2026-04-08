<script setup lang="ts">
import type { MenuItem } from '~/types/menu-item'

defineProps<{
  item: MenuItem
}>()

const dietaryLabels: Record<string, string> = {
  vegan: 'Vegan',
  vegetarian: 'Vegetarian',
  gluten_free: 'GF',
}
</script>

<template>
  <CvCard class="menu-item-card">
    <template #header>
      <div class="menu-item-card__image-container">
        <img
          v-if="item.imageUrl"
          :src="item.imageUrl"
          :alt="item.name"
          class="menu-item-card__image"
          loading="lazy"
        />
        <div v-else class="menu-item-card__image-placeholder" />
      </div>
    </template>

    <div class="menu-item-card__content">
      <h3 class="menu-item-card__name headline-sm">{{ item.name }}</h3>
      <p class="menu-item-card__description body-sm">{{ item.description }}</p>

      <div v-if="item.dietary.length || item.allergens.length" class="menu-item-card__badges">
        <CvBadge v-for="tag in item.dietary" :key="tag" size="sm">
          {{ dietaryLabels[tag] || tag }}
        </CvBadge>
        <CvBadge
          v-for="allergen in item.allergens"
          :key="allergen"
          size="sm"
          :variant="allergen === 'nuts' ? 'warning' : 'default'"
        >
          {{ allergen.charAt(0).toUpperCase() + allergen.slice(1) }}
        </CvBadge>
      </div>

      <p class="menu-item-card__price title-lg">{{ formatCurrency(item.price) }}</p>
    </div>
  </CvCard>
</template>

<style scoped>
.menu-item-card__image-container {
  aspect-ratio: 4 / 3;
  background-color: var(--surface-container-lowest);
  border-radius: 0.125rem;
  overflow: hidden;
  margin-bottom: var(--space-sm);
}

.menu-item-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.menu-item-card__image-placeholder {
  width: 100%;
  height: 100%;
  background-color: var(--surface-container-lowest);
}

.menu-item-card__content {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.menu-item-card__name {
  color: var(--on-surface);
  margin: 0;
}

.menu-item-card__description {
  color: var(--tertiary);
  margin: 0;
  line-height: 1.5;
}

.menu-item-card__badges {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
}

.menu-item-card__price {
  color: var(--secondary);
  margin: 0;
}
</style>
