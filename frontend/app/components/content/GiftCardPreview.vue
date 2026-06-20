<script setup lang="ts">
import {
  editionMeta,
  scheduleLongLabel,
  scheduleShortLabel,
} from '~/composables/useGiftCardComposer'
import type { PurchaseGiftCardData } from '~/types/gift-card'
import { formatCurrency, formatCurrencyParts } from '~/utils/formatCurrency'

const emit = defineEmits<{
  submit: [payload: Omit<PurchaseGiftCardData, 'paymentMethodId' | 'idempotencyKey'>]
}>()

import { fallbackSiteContacts } from '~/data/siteContacts'
import { useSiteContacts, resolveSiteContacts } from '~/composables/useSiteContent'

const { data: contactsData } = useSiteContacts()
const contacts = computed(() =>
  resolveSiteContacts(contactsData.value?.data?.contacts ?? null, fallbackSiteContacts),
)

const composer = useGiftCardComposer()
const { state } = composer

const loading = ref(false)
const orderNumber = 'FC—8AC4'

const editionLabel = computed(() => editionMeta(state.value.edition).label)

const deliveryLabel = computed(() =>
  state.value.deliveryMethod === 'email' ? 'By email' : 'Printed & posted',
)

const scheduleLabel = computed(() =>
  scheduleLongLabel(state.value.schedule, state.value.scheduleDateIso),
)

const summaryDelivery = computed(() => {
  const prefix = state.value.deliveryMethod === 'email' ? 'Email' : 'Posted'
  const tail = scheduleShortLabel(state.value.schedule, state.value.scheduleDateIso)
  return `${prefix} · ${tail}`
})

const totalLabel = computed(() => formatCurrency(state.value.amountCents))
const totalParts = computed(() => formatCurrencyParts(state.value.amountCents))

const previewSalutation = computed(() => state.value.recipientName.trim() || 'A friend')
const previewMessage = computed(() => state.value.message.trim() || 'Enjoy the films.')
const previewSender = computed(() => state.value.senderName.trim() || 'A patron')

async function handleSubmit(): Promise<void> {
  if (!composer.validate()) {
    useToast().show({
      message: 'Please complete the highlighted fields before sending.',
      type: 'error',
    })
    return
  }

  loading.value = true
  try {
    emit('submit', {
      amount: state.value.amountCents,
      recipientEmail: state.value.recipientEmail.trim(),
      recipientName: state.value.recipientName.trim(),
      senderName: state.value.senderName.trim(),
      message: state.value.message.trim() || null,
      edition: state.value.edition,
      deliveryMethod: state.value.deliveryMethod,
      scheduledSendAt: composer.resolveScheduledSendAt(),
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <aside class="gift-card-preview">
    <GiftCardVisual
      :amount-cents="state.amountCents"
      :edition="state.edition"
    />

    <!-- Message preview card -->
    <div class="gift-card-preview__msg">
      <div class="gift-card-preview__msg-stamp">
        <span><b>Delivery</b> · {{ deliveryLabel }}</span>
        <span>{{ scheduleLabel }}</span>
      </div>
      <span class="gift-card-preview__msg-salutation">For {{ previewSalutation }},</span>
      <p class="gift-card-preview__msg-body">{{ previewMessage }}</p>
      <span class="gift-card-preview__msg-signoff">— <b>{{ previewSender }}</b></span>
    </div>

    <!-- Order summary -->
    <div class="gift-card-preview__summary">
      <h4 class="gift-card-preview__summary-h">
        Order summary <span class="gift-card-preview__summary-no">No. {{ orderNumber }}</span>
      </h4>
      <div class="gift-card-preview__row"><span>Card value</span><b>{{ totalLabel }}</b></div>
      <div class="gift-card-preview__row"><span>Card edition</span><b>{{ editionLabel }}</b></div>
      <div class="gift-card-preview__row"><span>Delivery</span><b>{{ summaryDelivery }}</b></div>
      <div class="gift-card-preview__row"><span>Processing fee</span><b>$0.00</b></div>
      <div class="gift-card-preview__total">
        <span class="gift-card-preview__total-l">Total · charged today</span>
        <span class="gift-card-preview__total-v">
          <span class="gift-card-preview__total-cur">$</span>{{ totalParts.whole }}<span
            class="gift-card-preview__total-dec"
          >.{{ totalParts.dec }}</span>
        </span>
      </div>
      <CvButton
        variant="primary"
        block
        :loading="loading"
        :aria-label="`Send the gift, ${totalLabel} total`"
        @click="handleSubmit"
      >
        Send the gift
        <template #icon-right><span aria-hidden="true">→</span></template>
      </CvButton>
      <p class="gift-card-preview__fine">
        Cards never expire and are valid at all Final Cut cinemas. By proceeding you agree to our
        <NuxtLink to="/terms">gift card terms</NuxtLink>. Need help?
        <a :href="`mailto:${contacts.conciergeEmail}`">Concierge · {{ contacts.footerPhone }}</a>.
      </p>
    </div>
  </aside>
</template>

<style scoped>
.gift-card-preview {
  position: sticky;
  top: 7.5rem;
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

/* Message preview */
.gift-card-preview__msg {
  background: var(--surface-container-low);
  border: 1px solid rgba(87, 66, 62, 0.2);
  border-radius: var(--radius-card);
  padding: var(--space-lg);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.gift-card-preview__msg-stamp {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 0.5625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding-bottom: 0.625rem;
  border-bottom: 1px dashed rgba(87, 66, 62, 0.3);
}

.gift-card-preview__msg-stamp b {
  color: var(--secondary);
  font-weight: 500;
}

.gift-card-preview__msg-salutation {
  font-family: var(--font-display);
  font-size: 1rem;
  font-style: italic;
  color: var(--tertiary);
}

.gift-card-preview__msg-body {
  font-family: var(--font-body);
  font-size: 1.0625rem;
  line-height: 1.55;
  color: var(--on-surface);
  font-style: italic;
  text-wrap: pretty;
  min-height: 3rem;
  margin: 0;
}

.gift-card-preview__msg-signoff {
  font-family: var(--font-display);
  font-size: 1rem;
  color: var(--tertiary);
  margin-top: 0.25rem;
}

.gift-card-preview__msg-signoff b {
  color: var(--on-surface);
  font-weight: 500;
  font-style: italic;
}

/* Summary */
.gift-card-preview__summary {
  background: var(--surface-container-low);
  border: 1px solid rgba(87, 66, 62, 0.25);
  border-radius: var(--radius-card);
  padding: var(--space-lg);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.gift-card-preview__summary-h {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: 0.04em;
  color: var(--on-surface);
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-bottom: 0.625rem;
  border-bottom: 1px solid rgba(87, 66, 62, 0.2);
  margin: 0;
}

.gift-card-preview__summary-no {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  font-weight: 500;
}

.gift-card-preview__row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 0.875rem;
  color: var(--tertiary);
}

.gift-card-preview__row b {
  font-family: var(--font-display);
  color: var(--on-surface);
  font-weight: 500;
  letter-spacing: -0.01em;
}

.gift-card-preview__total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-top: var(--space-md);
  margin-top: 0.25rem;
  border-top: 1px solid rgba(87, 66, 62, 0.25);
}

.gift-card-preview__total-l {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.gift-card-preview__total-v {
  font-family: var(--font-display);
  font-size: 2rem;
  letter-spacing: -0.02em;
  color: var(--secondary);
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.gift-card-preview__total-cur {
  font-size: 0.55em;
  color: var(--tertiary);
  vertical-align: 0.4em;
  margin-right: 0.05em;
}

.gift-card-preview__total-dec {
  font-size: 0.55em;
  color: var(--tertiary);
  vertical-align: 0.4em;
  margin-left: 0.05em;
}

.gift-card-preview__fine {
  font-size: 0.6875rem;
  color: var(--on-tertiary-fixed-variant);
  line-height: 1.5;
  text-wrap: pretty;
  margin: 0;
}

.gift-card-preview__fine a {
  color: var(--secondary);
  border-bottom: 1px solid rgba(218, 199, 105, 0.3);
}

@media (max-width: 64rem) {
  .gift-card-preview {
    position: static;
  }
}
</style>
