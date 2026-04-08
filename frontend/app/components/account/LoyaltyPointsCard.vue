<script setup lang="ts">
const props = defineProps<{
  points: number
  tier: 'member' | 'premier'
  premierExpiry: string | null
}>()

const tierLabel = computed(() => {
  return props.tier.charAt(0).toUpperCase() + props.tier.slice(1)
})

const isPremier = computed(() => props.tier === 'premier')

const premierPerks = [
  '10% food discount',
  'Birthday ticket',
  'Early seat access',
]
</script>

<template>
  <CvCard variant="high" class="loyalty-card">
    <div class="loyalty-card__header">
      <CvBadge :variant="isPremier ? 'accent' : 'default'">
        {{ tierLabel }}
      </CvBadge>
    </div>

    <div class="loyalty-card__points">
      <span class="loyalty-card__points-value">{{ points.toLocaleString() }}</span>
      <span class="loyalty-card__points-label label-md">points</span>
    </div>

    <div v-if="isPremier" class="loyalty-card__premier">
      <p v-if="premierExpiry" class="loyalty-card__expiry body-sm">
        Expires {{ formatDate(premierExpiry) }}
      </p>
      <div class="loyalty-card__perks">
        <h4 class="loyalty-card__perks-title label-lg">Active Perks</h4>
        <ul class="loyalty-card__perks-list">
          <li v-for="perk in premierPerks" :key="perk" class="loyalty-card__perk body-sm">
            {{ perk }}
          </li>
        </ul>
      </div>
    </div>

    <div v-else class="loyalty-card__upgrade">
      <p class="loyalty-card__upgrade-text body-sm">Upgrade to Premier</p>
    </div>
  </CvCard>
</template>

<style scoped>
.loyalty-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.loyalty-card__header {
  display: flex;
  justify-content: flex-start;
}

.loyalty-card__points {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-xs);
}

.loyalty-card__points-value {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--secondary);
  line-height: 1;
}

.loyalty-card__points-label {
  color: var(--tertiary);
}

.loyalty-card__premier {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.loyalty-card__expiry {
  color: var(--tertiary);
  margin: 0;
}

.loyalty-card__perks {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.loyalty-card__perks-title {
  color: var(--on-surface);
  margin: 0;
}

.loyalty-card__perks-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.loyalty-card__perk {
  color: var(--tertiary);
}

.loyalty-card__upgrade {
  text-align: center;
}

.loyalty-card__upgrade-text {
  color: var(--secondary);
  margin: 0;
}
</style>
