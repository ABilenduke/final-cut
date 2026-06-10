<script setup lang="ts">
import { membershipContent } from '~/data/homepage'
import { useHomeContent, resolveMembershipContent } from '~/composables/useSiteContent'

// Admin-editable via the Home page content form (admin-v2 Plan 15); the
// static copy stays as the fallback until the first admin save.
const { data } = useHomeContent()
const m = computed(() =>
  resolveMembershipContent(data.value?.data?.membership ?? null, membershipContent),
)
</script>

<template>
  <section class="membership" aria-labelledby="membership-heading">
    <div class="membership__inner">
      <!-- The membership "card" — an actual visual card -->
      <div class="membership__card" aria-hidden="true">
        <span class="membership__card-tier">{{ m.cardTier }}</span>
        <span class="membership__card-num">{{ m.cardNumber }}</span>

        <h3 class="membership__card-title">
          {{ m.cardTitle }}<br><em>{{ m.cardTitleEmphasis }}</em>
        </h3>

        <div class="membership__card-row">
          <span>{{ m.cardValidThrough }}</span>
          <b>{{ m.cardSociety }}</b>
        </div>
      </div>

      <!-- Copy block -->
      <div class="membership__body">
        <div class="membership__eyebrow">{{ m.eyebrow }}</div>
        <h2 id="membership-heading" class="membership__title">
          {{ m.title }} <em>{{ m.titleEmphasis }}</em>
        </h2>
        <p class="membership__copy">{{ m.copy }}</p>

        <ol class="membership__perks">
          <li
            v-for="(perk, i) in m.perks"
            :key="perk.title"
            class="membership__perk"
          >
            <span class="membership__perk-num">{{ String(i + 1).padStart(2, '0') }}</span>
            <div class="membership__perk-body">
              <b>{{ perk.title }}</b>
              <span>{{ perk.detail }}</span>
            </div>
          </li>
        </ol>

        <div class="membership__actions">
          <CvButton variant="primary" href="/account">
            {{ m.priceLabel }}
          </CvButton>
          <NuxtLink to="/account" class="membership__link">
            {{ m.ctaLabel }} &rarr;
          </NuxtLink>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.membership {
  background-color: var(--surface-container-lowest);
  padding-block: var(--space-4xl);
  padding-inline: var(--space-md);
  position: relative;
  overflow: hidden;
  isolation: isolate;
}

@media (min-width: 40rem) {
  .membership { padding-inline: var(--space-xl); }
}

@media (min-width: 60rem) {
  .membership { padding-inline: var(--space-2xl); }
}

.membership::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: var(--z-recessed);
  background: radial-gradient(ellipse 60% 80% at 85% 50%, rgb(85 0 0 / 0.25), transparent 70%);
}

.membership__inner {
  max-width: 90rem;
  margin-inline: auto;
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-2xl);
  align-items: center;
}

@media (min-width: 60rem) {
  .membership__inner {
    grid-template-columns: 1fr 1fr;
    gap: var(--space-3xl);
  }
}

/* ——— Card visual ——— */
.membership__card {
  position: relative;
  padding: var(--space-2xl);
  min-height: 22.5rem;
  background: linear-gradient(145deg, #1a0a0a, var(--surface-container-lowest));
  border-radius: var(--radius-sm);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  justify-content: flex-end;
  overflow: hidden;
  isolation: isolate;
}

.membership__card::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: var(--z-recessed);
  background:
    radial-gradient(circle at 20% 30%, rgb(218 199 105 / 0.15), transparent 50%),
    radial-gradient(circle at 80% 70%, rgb(85 0 0 / 0.4), transparent 60%);
}

.membership__card::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: var(--z-recessed);
  background: repeating-linear-gradient(135deg, transparent 0 0.25rem, rgb(218 199 105 / 0.025) 0.25rem 0.3125rem);
}

.membership__card-tier {
  position: absolute;
  top: var(--space-lg);
  left: var(--space-2xl);
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--secondary);
}

.membership__card-num {
  position: absolute;
  top: var(--space-lg);
  right: var(--space-2xl);
  font-family: var(--font-display);
  font-size: 0.875rem;
  letter-spacing: 0.3em;
  color: var(--on-tertiary-fixed-variant);
}

.membership__card-title {
  font-family: var(--font-display);
  font-size: 2.5rem;
  line-height: 0.95;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin: 0;
}

.membership__card-title em {
  font-style: italic;
  color: var(--secondary);
}

.membership__card-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-md);
  border-top: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.2);
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.membership__card-row b {
  font-family: var(--font-display);
  color: var(--on-surface);
  font-size: 1.125rem;
  letter-spacing: -0.01em;
  text-transform: none;
  font-weight: 500;
}

/* ——— Body ——— */
.membership__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.membership__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.membership__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 3.5vw, 3rem);
  line-height: 1.02;
  letter-spacing: -0.025em;
  color: var(--on-surface);
  margin: 0 0 var(--space-md);
  text-wrap: balance;
}

.membership__title em {
  font-style: italic;
  color: var(--tertiary);
}

.membership__copy {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1.0625rem;
  line-height: 1.55;
  max-width: 46ch;
  margin: 0 0 var(--space-lg);
  text-wrap: pretty;
}

.membership__perks {
  list-style: none;
  padding: 0;
  margin: 0 0 var(--space-xl);
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-md) var(--space-lg);
}

@media (min-width: 40rem) {
  .membership__perks {
    grid-template-columns: 1fr 1fr;
  }
}

.membership__perk {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.membership__perk-num {
  font-family: var(--font-display);
  font-size: 0.875rem;
  color: var(--secondary);
  letter-spacing: 0.1em;
  min-width: 2rem;
  padding-top: 0.125rem;
}

.membership__perk-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
  font-family: var(--font-body);
  font-size: 0.9375rem;
  line-height: 1.35;
  color: var(--on-surface);
}

.membership__perk-body b {
  font-weight: 500;
}

.membership__perk-body span {
  color: var(--on-tertiary-fixed-variant);
  font-size: 0.8125rem;
}

.membership__actions {
  display: flex;
  gap: var(--space-md);
  align-items: center;
  flex-wrap: wrap;
}

.membership__link {
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: 0.875rem;
  text-decoration: none;
  padding-block: var(--space-xs);
  border-bottom: var(--border-hairline) solid transparent;
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.membership__link:hover { border-bottom-color: var(--secondary); }
</style>
