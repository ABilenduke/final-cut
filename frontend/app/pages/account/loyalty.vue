<script setup lang="ts">
import type { LoyaltyHistoryEntry } from '~/composables/useAccount'

definePageMeta({ layout: 'account', middleware: 'auth' })
useHead({
  title: 'Loyalty Program — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const { loyalty } = useAccount()
const { data: loyaltyData } = await loyalty()
</script>

<template>
  <div class="loyalty-page">
    <h1 class="loyalty-page__title">Loyalty Program</h1>

    <div class="loyalty-page__content">
      <LoyaltyPointsCard
        v-if="loyaltyData?.data"
        :points="loyaltyData.data.points"
        :tier="loyaltyData.data.tier"
        :premier-expiry="loyaltyData.data.premierExpiry"
      />

      <section v-if="loyaltyData?.data?.history?.length" class="loyalty-page__history">
        <h2 class="loyalty-page__section-title">Points History</h2>
        <ul class="loyalty-page__history-list">
          <li
            v-for="(entry, index) in (loyaltyData.data.history as LoyaltyHistoryEntry[])"
            :key="index"
            class="loyalty-page__history-item"
          >
            <span class="loyalty-page__history-description">{{ entry.description }}</span>
            <span
              class="loyalty-page__history-points"
              :class="{
                'loyalty-page__history-points--earned': entry.points > 0,
                'loyalty-page__history-points--redeemed': entry.points < 0,
              }"
            >
              {{ entry.points > 0 ? '+' : '' }}{{ entry.points }}
            </span>
          </li>
        </ul>
      </section>

      <section
        v-if="loyaltyData?.data?.tier === 'member'"
        class="loyalty-page__upgrade"
      >
        <CvCard variant="high">
          <h2 class="loyalty-page__section-title">Upgrade to Premier</h2>
          <p class="loyalty-page__upgrade-text">
            Unlock premium perks with a Premier membership:
          </p>
          <ul class="loyalty-page__perks-list">
            <li>10% discount on all food & drinks</li>
            <li>Free birthday ticket every year</li>
            <li>Early access to seat selection</li>
            <li>Exclusive member-only events</li>
          </ul>
        </CvCard>
      </section>
    </div>
  </div>
</template>

<style scoped>
.loyalty-page__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-lg);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-xl);
}

.loyalty-page__content {
  max-width: 40rem;
  margin-inline: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.loyalty-page__section-title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-md);
}

.loyalty-page__history-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.loyalty-page__history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-sm) 0;
  border-bottom: var(--border-hairline) solid color-mix(in srgb, var(--outline-variant) 15%, transparent);
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
}

.loyalty-page__history-description {
  flex: 1;
  min-width: 0;
}

.loyalty-page__history-points {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  flex-shrink: 0;
  margin-left: var(--space-md);
}

.loyalty-page__history-points--earned {
  color: var(--secondary);
}

.loyalty-page__history-points--redeemed {
  color: var(--tertiary);
}

.loyalty-page__upgrade-text {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--tertiary);
  margin-bottom: var(--space-md);
}

.loyalty-page__perks-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
}

.loyalty-page__perks-list li::before {
  content: '—';
  color: var(--secondary);
  margin-right: var(--space-sm);
}
</style>
