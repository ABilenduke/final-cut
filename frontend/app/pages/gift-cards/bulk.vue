<script setup lang="ts">
import { fallbackSiteContacts } from '~/data/siteContacts'
import { useSiteContacts, resolveSiteContacts } from '~/composables/useSiteContent'

const { data: contactsData } = useSiteContacts()
const contacts = computed(() =>
  resolveSiteContacts(contactsData.value?.data?.contacts ?? null, fallbackSiteContacts),
)

useHead({
  title: 'Bulk Gifting — Final Cut',
  meta: [
    {
      name: 'description',
      content:
        'Bulk gift cards for corporate gifting, production crews, and press. Customised wordmark, single or fanned-out delivery, 5-business-day lead time.',
    },
    { property: 'og:title', content: 'Bulk Gifting — Final Cut' },
    { property: 'og:type', content: 'website' },
  ],
})
</script>

<template>
  <div class="bulk-page">
    <div class="bulk-page__inner">
      <p class="bulk-page__eyebrow">For Corporate &amp; Partnerships</p>
      <h1 class="bulk-page__title">
        Bulk gifting,<br><em>with intention.</em>
      </h1>
      <p class="bulk-page__lede">
        Twenty cards or more, customised with your wordmark on the back, delivered to a single
        address or fanned out across your roster. Bulk-gifting forms and a downloadable PDF brief
        are forthcoming — until then our concierge will quote and schedule by hand.
      </p>

      <div class="bulk-page__stats">
        <div class="bulk-page__stat">
          <div class="bulk-page__stat-l">Minimum order</div>
          <div class="bulk-page__stat-v">20 <em>cards</em></div>
        </div>
        <div class="bulk-page__stat">
          <div class="bulk-page__stat-l">Volume discount</div>
          <div class="bulk-page__stat-v">Up to <em>15%</em></div>
        </div>
        <div class="bulk-page__stat">
          <div class="bulk-page__stat-l">Lead time</div>
          <div class="bulk-page__stat-v">5 <em>business days</em></div>
        </div>
      </div>

      <div class="bulk-page__cta">
        <CvButton
          variant="primary"
          :href="`mailto:${contacts.conciergeEmail}?subject=Bulk%20gift%20card%20enquiry`"
        >
          Speak to our concierge →
        </CvButton>
        <NuxtLink to="/gift-cards" class="bulk-page__back">← Back to gift cards</NuxtLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bulk-page {
  max-width: 90rem;
  margin: 0 auto;
  padding: var(--space-4xl) var(--space-2xl) var(--space-3xl);
}

.bulk-page__inner {
  max-width: 40rem;
  margin: 0 auto;
}

.bulk-page__eyebrow {
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0 0 var(--space-md);
}

.bulk-page__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.bulk-page__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.25rem);
  line-height: 1.05;
  letter-spacing: -0.025em;
  text-wrap: balance;
  margin: 0 0 var(--space-lg);
}

.bulk-page__title em {
  font-style: italic;
  color: var(--secondary);
}

.bulk-page__lede {
  color: var(--tertiary);
  font-size: 1.0625rem;
  line-height: 1.55;
  text-wrap: pretty;
  margin: 0 0 var(--space-2xl);
}

.bulk-page__stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-lg);
  padding: var(--space-lg) 0;
  border-top: 1px solid rgba(87, 66, 62, 0.2);
  border-bottom: 1px solid rgba(87, 66, 62, 0.2);
}

.bulk-page__stat-l {
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.bulk-page__stat-v {
  font-family: var(--font-display);
  font-size: 1.625rem;
  letter-spacing: -0.02em;
  line-height: 1.1;
  color: var(--on-surface);
  font-weight: 500;
  margin-top: 0.4rem;
}

.bulk-page__stat-v em {
  font-style: italic;
  color: var(--secondary);
}

.bulk-page__cta {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  margin-top: var(--space-2xl);
  align-items: flex-start;
}

.bulk-page__back {
  color: var(--tertiary);
  font-size: 0.9375rem;
  border-bottom: 1px solid transparent;
  transition: color 200ms, border-color 200ms;
}

.bulk-page__back:hover {
  color: var(--secondary);
  border-bottom-color: var(--secondary);
}

@media (max-width: 40rem) {
  .bulk-page {
    padding: var(--space-2xl) var(--space-md);
  }
  .bulk-page__stats {
    grid-template-columns: 1fr;
    gap: var(--space-md);
  }
}
</style>
