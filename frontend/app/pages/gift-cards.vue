<script setup lang="ts">
import { fallbackSiteContacts } from '~/data/siteContacts'
import { useSiteContacts, resolveSiteContacts } from '~/composables/useSiteContent'
import { useGiftCardComposer } from '~/composables/useGiftCardComposer'
import { formatCurrency } from '~/utils/formatCurrency'
import type { GiftCard, PurchaseGiftCardData } from '~/types/gift-card'

// Payment step (admin-v3 Plan 03): the composer's Purchase button hands the
// composed payload up here; the modal collects the card and completes the
// Stripe purchase/3DS/confirm flow.
type ComposedPayload = Omit<PurchaseGiftCardData, 'paymentMethodId' | 'idempotencyKey'>

const pendingPayload = ref<ComposedPayload | null>(null)
const purchasedCard = ref<GiftCard | null>(null)

function onCompose(payload: ComposedPayload): void {
  pendingPayload.value = payload
}

function onPurchased(card: GiftCard): void {
  purchasedCard.value = card
  pendingPayload.value = null
  useGiftCardComposer().reset()
  useToast().show({ message: 'Gift card purchased.', type: 'success' })
}

const { data: contactsData } = useSiteContacts()
const contacts = computed(() =>
  resolveSiteContacts(contactsData.value?.data?.contacts ?? null, fallbackSiteContacts),
)

definePageMeta({
  hideTicker: true,
})

useHead({
  title: 'Gift Cards — Final Cut',
  meta: [
    {
      name: 'description',
      content:
        'A Final Cut gift card: redeemable on any film, any seat, any provision from the bar. Delivered by email or printed on heavy stock and posted in a black sleeve.',
    },
    { property: 'og:title', content: 'Gift Cards — Final Cut' },
    {
      property: 'og:description',
      content:
        'A Final Cut gift card: redeemable on any film, any seat, any provision from the bar.',
    },
    { property: 'og:type', content: 'website' },
  ],
})

interface FaqItem {
  question: string
  answer: string
}

const FAQ: FaqItem[] = [
  {
    question: 'Where can the recipient redeem this card?',
    answer:
      'All Final Cut cinemas, online or at the box office. Redeemable against tickets, food & drink, and Reel Society membership. Not valid against private screenings or third-party events.',
  },
  {
    question: 'Does the card ever expire?',
    answer: 'No. The remaining balance carries forward indefinitely.',
  },
  {
    question: 'Can I split the value across multiple recipients?',
    answer:
      'Use the bulk-gifting flow above for two or more recipients. Each card gets its own message and delivery date.',
  },
  {
    question: "Is the recipient's name printed on the card?",
    answer:
      "Email cards show the recipient's name in the salutation. Posted cards remain unnamed — they're transferrable, like cash for cinema.",
  },
  {
    question: 'What if the code is lost?',
    answer:
      "Email us with the order number — every card is registered. We'll reissue against the unused balance within 24 hours.",
  },
]

const openFaqIndex = ref<number | null>(0)

function toggleFaq(index: number): void {
  openFaqIndex.value = openFaqIndex.value === index ? null : index
}
</script>

<template>
  <div class="gift-cards-page">
    <GiftCardBalanceStrip />

    <main class="gift-cards-page__main">
      <!-- Editorial masthead -->
      <section class="gift-cards-page__top">
        <div>
          <p class="gift-cards-page__eyebrow">Vol. XXIII · Reel Society Gift Programme</p>
          <h1 class="gift-cards-page__title">
            The gift of <em>two hours</em><br>in the <span class="gift-cards-page__title-accent">dark.</span>
          </h1>
          <p class="gift-cards-page__lede">
            A cinema gift card is a quiet, deliberate thing: redeemable on any film, any seat, any
            provision from the bar. Delivered by email or printed on heavy stock and posted in a
            black sleeve.
          </p>
        </div>
        <div class="gift-cards-page__meta">
          <span class="gift-cards-page__meta-pill">In stock · Ships same day</span>
          <b>$25 — $500</b>
          <span>Never expires · Multi-cinema</span>
        </div>
      </section>

      <!-- Composer + Preview -->
      <div class="gift-cards-page__grid">
        <GiftCardComposer />
        <GiftCardPreview @submit="onCompose" />
      </div>

      <GiftCardPaymentModal
        v-if="pendingPayload"
        :payload="pendingPayload"
        :amount-label="formatCurrency(pendingPayload.amount)"
        @close="pendingPayload = null"
        @purchased="onPurchased"
      />

      <section
        v-if="purchasedCard"
        class="gift-cards-page__purchased"
        aria-live="polite"
        data-testid="gc-purchased"
      >
        <p class="gift-cards-page__eyebrow">Order confirmed</p>
        <h2 class="gift-cards-page__h2">It's on its <em>way.</em></h2>
        <p class="gift-cards-page__purchased-detail">
          {{ formatCurrency(purchasedCard.initialBalance ?? 0) }} gift card
          <template v-if="purchasedCard.recipientEmail">
            for {{ purchasedCard.recipientEmail }}
          </template>
          — a confirmation email is on the way to the recipient.
        </p>
      </section>

      <!-- Below the fold: corporate + FAQ -->
      <section class="gift-cards-page__below">
        <div>
          <p class="gift-cards-page__eyebrow">For Corporate &amp; Partnerships</p>
          <h2 class="gift-cards-page__h2">Bulk gifting, <em>with intention.</em></h2>
          <p class="gift-cards-page__below-lede">
            Twenty cards or more, customised with your wordmark on the back, delivered to a single
            address or fanned out across your roster. We also handle film-night vouchers for
            production crews and press.
          </p>
          <div class="gift-cards-page__corp-actions">
            <a
              class="gift-cards-page__btn-ghost"
              :href="`mailto:${contacts.conciergeEmail}?subject=Bulk%20gift%20card%20enquiry`"
            >
              Speak to our concierge →
            </a>
            <NuxtLink to="/gift-cards/bulk" class="gift-cards-page__btn-ghost">
              Download bulk PDF →
            </NuxtLink>
          </div>
          <div class="gift-cards-page__corp-stats">
            <div class="gift-cards-page__corp-stat">
              <div class="gift-cards-page__corp-stat-l">Minimum order</div>
              <div class="gift-cards-page__corp-stat-v">20 <em>cards</em></div>
            </div>
            <div class="gift-cards-page__corp-stat">
              <div class="gift-cards-page__corp-stat-l">Volume discount</div>
              <div class="gift-cards-page__corp-stat-v">Up to <em>15%</em></div>
            </div>
            <div class="gift-cards-page__corp-stat">
              <div class="gift-cards-page__corp-stat-l">Lead time</div>
              <div class="gift-cards-page__corp-stat-v">5 <em>business days</em></div>
            </div>
          </div>
        </div>

        <div>
          <p class="gift-cards-page__eyebrow">Reading Material</p>
          <h2 class="gift-cards-page__h2">Questions, <em>answered briefly.</em></h2>
          <div class="gift-cards-page__faq">
            <div
              v-for="(item, index) in FAQ"
              :key="item.question"
              class="gift-cards-page__faq-item"
              :class="{ 'gift-cards-page__faq-item--open': openFaqIndex === index }"
            >
              <button
                :id="`gift-cards-faq-q-${index}`"
                type="button"
                class="gift-cards-page__faq-q"
                :aria-expanded="openFaqIndex === index"
                :aria-controls="`gift-cards-faq-${index}`"
                @click="toggleFaq(index)"
              >
                <span class="gift-cards-page__faq-n">{{ String(index + 1).padStart(2, '0') }}</span>
                <span class="gift-cards-page__faq-q-text">{{ item.question }}</span>
                <span class="gift-cards-page__faq-pl" aria-hidden="true" />
              </button>
              <div
                :id="`gift-cards-faq-${index}`"
                class="gift-cards-page__faq-a"
                role="region"
                :aria-labelledby="`gift-cards-faq-q-${index}`"
              >
                <div class="gift-cards-page__faq-a-inner">{{ item.answer }}</div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.gift-cards-page {
  --balance-strip-height: 2.5rem;
}

.gift-cards-page__main {
  max-width: 90rem;
  margin: 0 auto;
  padding: calc(var(--balance-strip-height) + var(--space-xl)) var(--space-2xl) var(--space-3xl);
}

/* Editorial masthead */
.gift-cards-page__top {
  padding: var(--space-xl) 0 var(--space-lg);
  display: grid;
  grid-template-columns: 1fr auto;
  gap: var(--space-lg);
  align-items: flex-end;
  border-bottom: 1px solid rgba(87, 66, 62, 0.2);
  margin-bottom: var(--space-2xl);
}

.gift-cards-page__eyebrow {
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0 0 0.5rem;
}

.gift-cards-page__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.gift-cards-page__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2.5rem, 5.5vw, 4.25rem);
  line-height: 0.98;
  letter-spacing: -0.03em;
  text-wrap: balance;
  max-width: 14ch;
  margin: 0;
}

.gift-cards-page__title em {
  font-style: italic;
  color: var(--tertiary);
}

.gift-cards-page__title-accent {
  color: var(--secondary);
  font-style: italic;
}

.gift-cards-page__lede {
  color: var(--tertiary);
  font-size: 1.0625rem;
  line-height: 1.55;
  max-width: 48ch;
  margin: var(--space-md) 0 0;
  text-wrap: pretty;
}

.gift-cards-page__meta {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  align-items: flex-end;
}

.gift-cards-page__meta b {
  font-family: var(--font-display);
  color: var(--on-surface);
  font-weight: 500;
  font-size: 0.9375rem;
  letter-spacing: 0.06em;
  text-transform: none;
}

.gift-cards-page__meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--secondary);
}

.gift-cards-page__meta-pill::before {
  content: '';
  width: 0.3125rem;
  height: 0.3125rem;
  border-radius: 50%;
  background: var(--secondary);
}

/* Composer + preview grid */
.gift-cards-page__grid {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
  gap: var(--space-2xl);
  align-items: start;
}

/* Below the fold */
.gift-cards-page__below {
  margin-top: var(--space-4xl);
  padding-top: var(--space-2xl);
  border-top: 1px solid rgba(87, 66, 62, 0.2);
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3xl);
  align-items: start;
}

.gift-cards-page__h2 {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(1.75rem, 3vw, 2.5rem);
  letter-spacing: -0.025em;
  line-height: 1.05;
  margin: 0 0 var(--space-md);
  text-wrap: balance;
  max-width: 18ch;
}

.gift-cards-page__h2 em {
  font-style: italic;
  color: var(--tertiary);
}

.gift-cards-page__purchased {
  margin-top: var(--space-2xl);
  padding: var(--space-xl);
  background-color: var(--surface-container-low);
  border-radius: var(--radius-sm);
}

.gift-cards-page__purchased-detail {
  margin: 0;
  color: var(--tertiary);
  font-size: 1rem;
  line-height: 1.6;
}

.gift-cards-page__below-lede {
  color: var(--tertiary);
  font-size: 1rem;
  line-height: 1.55;
  max-width: 46ch;
  text-wrap: pretty;
}

.gift-cards-page__corp-actions {
  display: flex;
  gap: var(--space-md);
  align-items: center;
  margin-top: var(--space-lg);
  flex-wrap: wrap;
}

.gift-cards-page__btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  height: 3rem;
  padding: 0 1rem;
  color: var(--on-surface);
  border-bottom: 1px solid var(--outline);
  font-size: 0.9375rem;
  letter-spacing: 0.02em;
  transition: color 200ms, border-color 200ms;
  border-radius: 0;
  text-decoration: none;
}

.gift-cards-page__btn-ghost:hover {
  color: var(--secondary);
  border-bottom-color: var(--secondary);
}

.gift-cards-page__corp-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-lg);
  margin-top: var(--space-xl);
  padding-top: var(--space-lg);
  border-top: 1px solid rgba(87, 66, 62, 0.2);
}

.gift-cards-page__corp-stat-l {
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.gift-cards-page__corp-stat-v {
  font-family: var(--font-display);
  font-size: 1.625rem;
  letter-spacing: -0.02em;
  line-height: 1.1;
  color: var(--on-surface);
  font-weight: 500;
  margin-top: 0.4rem;
}

.gift-cards-page__corp-stat-v em {
  font-style: italic;
  color: var(--secondary);
}

/* FAQ */
.gift-cards-page__faq {
  display: flex;
  flex-direction: column;
}

.gift-cards-page__faq-item {
  border-top: 1px solid rgba(87, 66, 62, 0.2);
}

.gift-cards-page__faq-item:last-child {
  border-bottom: 1px solid rgba(87, 66, 62, 0.2);
}

.gift-cards-page__faq-q {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-md);
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  color: var(--on-surface);
  width: 100%;
  background: none;
  border: none;
  cursor: pointer;
  padding: var(--space-md) 0;
  text-align: left;
}

.gift-cards-page__faq-n {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  color: var(--secondary);
  font-weight: 500;
  min-width: 2rem;
  font-family: var(--font-display);
}

.gift-cards-page__faq-q-text {
  flex: 1;
}

.gift-cards-page__faq-pl {
  width: 1rem;
  height: 1rem;
  position: relative;
  flex-shrink: 0;
  transition: transform 250ms var(--ease-standard);
}

.gift-cards-page__faq-pl::before,
.gift-cards-page__faq-pl::after {
  content: '';
  position: absolute;
  background: var(--on-tertiary-fixed-variant);
}

.gift-cards-page__faq-pl::before {
  top: 50%;
  left: 0;
  right: 0;
  height: 1px;
}

.gift-cards-page__faq-pl::after {
  left: 50%;
  top: 0;
  bottom: 0;
  width: 1px;
  transition: transform 250ms;
}

.gift-cards-page__faq-item--open .gift-cards-page__faq-pl::after {
  transform: scaleY(0);
}

/* Open/close uses a grid-row-template animation so answers of any height
 * fit without truncation — same pattern the design system's CvAccordion
 * uses. The wrapper holds `grid-template-rows: 0fr | 1fr`; the inner
 * `<div>` (rendered via the `:before/:after` of overflow:hidden) is the
 * grid track that grows. */
.gift-cards-page__faq-a {
  color: var(--tertiary);
  font-size: 0.9375rem;
  line-height: 1.6;
  display: grid;
  grid-template-rows: 0fr;
  padding: 0 0 0 2.625rem;
  margin-top: 0;
  transition: grid-template-rows 350ms var(--ease-standard), margin-top 350ms, padding-bottom 350ms;
}

.gift-cards-page__faq-a > * {
  min-height: 0;
  overflow: hidden;
}

.gift-cards-page__faq-item--open .gift-cards-page__faq-a {
  grid-template-rows: 1fr;
  margin-top: var(--space-md);
  padding-bottom: var(--space-md);
}

/* Responsive */
@media (max-width: 64rem) {
  .gift-cards-page__grid {
    grid-template-columns: 1fr;
  }
  .gift-cards-page__below {
    grid-template-columns: 1fr;
    gap: var(--space-2xl);
  }
}

@media (max-width: 40rem) {
  .gift-cards-page__main {
    padding-left: var(--space-md);
    padding-right: var(--space-md);
  }
  .gift-cards-page__top {
    grid-template-columns: 1fr;
  }
  .gift-cards-page__meta {
    text-align: left;
    align-items: flex-start;
  }
  .gift-cards-page__corp-stats {
    grid-template-columns: 1fr;
  }
}
</style>
