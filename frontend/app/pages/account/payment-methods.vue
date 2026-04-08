<script setup lang="ts">
definePageMeta({ layout: 'account', middleware: 'auth' })
useHead({
  title: 'Payment Methods — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const { paymentMethods, addPaymentMethod, removePaymentMethod } = useAccount()
const { show } = useToast()

const { data: methodsData, refresh } = await paymentMethods()

async function handleAdd() {
  try {
    await addPaymentMethod()
    await refresh()
    show({ message: 'Payment method added', type: 'success' })
  } catch {
    show({ message: 'Failed to add payment method. Please try again.', type: 'error' })
  }
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
        @add="handleAdd"
        @remove="handleRemove"
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
