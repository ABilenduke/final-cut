<script setup lang="ts">
interface PaymentMethod {
  id: string
  brand: string
  last4: string
  expMonth: number
  expYear: number
}

defineProps<{
  methods: PaymentMethod[]
}>()

const emit = defineEmits<{
  add: []
  remove: [id: string]
}>()

function formatExpiry(month: number, year: number): string {
  return `${String(month).padStart(2, '0')}/${String(year).slice(-2)}`
}

function formatBrand(brand: string): string {
  return brand.charAt(0).toUpperCase() + brand.slice(1)
}
</script>

<template>
  <div class="payment-methods">
    <div v-if="methods.length === 0" class="payment-methods__empty">
      <p class="payment-methods__empty-text body-md">No saved payment methods.</p>
    </div>

    <ul v-else class="payment-methods__list">
      <li v-for="method in methods" :key="method.id" class="payment-methods__item">
        <div class="payment-methods__card-info">
          <span class="payment-methods__brand label-lg">{{ formatBrand(method.brand) }}</span>
          <span class="payment-methods__number body-md">&bull;&bull;&bull;&bull; {{ method.last4 }}</span>
          <span class="payment-methods__expiry body-sm">Exp {{ formatExpiry(method.expMonth, method.expYear) }}</span>
        </div>
        <CvButton
          variant="tertiary"
          size="sm"
          @click="emit('remove', method.id)"
        >
          Remove
        </CvButton>
      </li>
    </ul>

    <div class="payment-methods__actions">
      <CvButton
        variant="secondary"
        @click="emit('add')"
      >
        Add Payment Method
      </CvButton>
    </div>
  </div>
</template>

<style scoped>
.payment-methods {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.payment-methods__empty {
  padding: var(--space-xl);
  text-align: center;
}

.payment-methods__empty-text {
  color: var(--tertiary);
  margin: 0;
}

.payment-methods__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.payment-methods__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-md);
  background-color: var(--surface-container-high);
  border-radius: 0.125rem;
}

.payment-methods__card-info {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.payment-methods__brand {
  color: var(--on-surface);
}

.payment-methods__number {
  color: var(--on-surface);
}

.payment-methods__expiry {
  color: var(--tertiary);
}

.payment-methods__actions {
  display: flex;
  justify-content: flex-start;
}
</style>
