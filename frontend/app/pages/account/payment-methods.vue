<script setup lang="ts">
definePageMeta({ layout: 'account', middleware: 'auth' })
useHead({
  title: 'Payment Methods — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const { paymentMethods, removePaymentMethod } = useAccount()
const { show } = useToast()

const { data: methodsData, refresh } = await paymentMethods()

// Add-card flow (admin-v4 Plan 02): the modal mints the SetupIntent and
// confirms the card via Stripe Elements; the bare POST alone could never
// attach a card.
const addingCard = ref(false)

async function handleAdded() {
  addingCard.value = false
  await refresh()
  show({ message: 'Payment method added', type: 'success' })
}

async function handleRemove(id: string) {
  try {
    await removePaymentMethod(id)
    await refresh()
    show({ message: 'Payment method removed', type: 'success' })
  } catch {
    show({ message: 'Failed to remove payment method. Please try again.', type: 'error' })
  }
}
</script>

<template>
  <div class="payment-page">
    <h1 class="payment-page__title">Payment Methods</h1>

    <div class="payment-page__content">
      <SavedPaymentMethods
        :methods="methodsData?.data ?? []"
        @add="addingCard = true"
        @remove="handleRemove"
      />

      <AddPaymentMethodModal
        v-if="addingCard"
        @close="addingCard = false"
        @added="handleAdded"
      />
    </div>
  </div>
</template>

<style scoped>
.payment-page__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-lg);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-xl);
}

.payment-page__content {
  max-width: 40rem;
  margin-inline: auto;
}
</style>
