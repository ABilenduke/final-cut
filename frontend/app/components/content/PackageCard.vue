<script setup lang="ts">
defineProps<{
  package: {
    name: string
    description: string
    startingPrice: number
    features: string[]
  }
}>()
</script>

<template>
  <CvCard class="package-card">
    <div class="package-card__content">
      <h3 class="package-card__name headline-sm">{{ package.name }}</h3>
      <p class="package-card__description body-sm">{{ package.description }}</p>

      <ul class="package-card__features">
        <li v-for="feature in package.features" :key="feature" class="package-card__feature body-sm">
          <svg
            class="package-card__check"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
          >
            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
          </svg>
          {{ feature }}
        </li>
      </ul>

      <p class="package-card__price title-lg">
        From {{ formatCurrency(package.startingPrice) }}
      </p>
    </div>
  </CvCard>
</template>

<style scoped>
.package-card__content {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.package-card__name {
  color: var(--on-surface);
  margin: 0;
}

.package-card__description {
  color: var(--tertiary);
  margin: 0;
  line-height: 1.5;
}

.package-card__features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.package-card__feature {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  color: var(--on-surface);
}

.package-card__check {
  width: var(--icon-sm);
  height: var(--icon-sm);
  flex-shrink: 0;
  color: var(--secondary);
}

.package-card__price {
  color: var(--secondary);
  margin: var(--space-sm) 0 0;
}
</style>
