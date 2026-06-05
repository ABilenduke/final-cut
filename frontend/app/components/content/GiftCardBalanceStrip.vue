<script setup lang="ts">
import { formatCurrency } from '~/utils/formatCurrency'

const code = ref('')
const balance = ref<number | null>(null)
const status = ref<string | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const { checkBalance } = useGiftCards()

const result = computed<string | null>(() => {
  if (error.value) return error.value
  if (balance.value === null) return null
  const fmt = formatCurrency(balance.value)
  const statusLabel = status.value ? capitalize(status.value) : ''
  return `Balance · ${fmt} · ${statusLabel}`
})

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

async function lookup(): Promise<void> {
  const trimmed = code.value.trim()
  if (!trimmed) {
    error.value = null
    balance.value = null
    status.value = null
    return
  }

  loading.value = true
  error.value = null
  try {
    const response = await checkBalance(trimmed)
    balance.value = response.data.balance
    status.value = response.data.status
  } catch (err: any) {
    balance.value = null
    status.value = null
    error.value = err?.errors?.[0]?.message || 'Card not found'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="gift-card-balance-strip" role="region" aria-label="Gift card balance lookup">
    <span class="gift-card-balance-strip__lede">
      <b>Check Balance</b>&nbsp;Enter a code to view remaining funds
    </span>
    <form class="gift-card-balance-strip__field" @submit.prevent="lookup">
      <input
        v-model="code"
        class="gift-card-balance-strip__input"
        placeholder="FC—XXXX XXXX XXXX"
        maxlength="32"
        aria-label="Gift card code"
        :disabled="loading"
      >
      <button type="submit" class="gift-card-balance-strip__btn" :disabled="loading">
        {{ loading ? 'Checking…' : 'Look up →' }}
      </button>
    </form>
    <span
      v-if="result"
      class="gift-card-balance-strip__result"
      :class="{ 'gift-card-balance-strip__result--error': !!error }"
      aria-live="polite"
    >
      {{ result }}
    </span>
  </div>
</template>

<style scoped>
.gift-card-balance-strip {
  position: fixed;
  top: var(--layout-header-height, 4.5rem);
  left: 0;
  right: 0;
  height: 2.5rem;
  z-index: var(--z-ticker);
  background: var(--surface-container-lowest);
  display: flex;
  align-items: center;
  padding: 0 var(--space-2xl);
  border-bottom: 1px solid rgba(87, 66, 62, 0.15);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  gap: var(--space-lg);
}

.gift-card-balance-strip__lede b {
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.22em;
}

.gift-card-balance-strip__field {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin-left: auto;
  background: var(--surface-container);
  padding: 0.25rem 0.625rem;
  border-radius: var(--radius-sm);
}

.gift-card-balance-strip__input {
  width: 11rem;
  font-size: 0.75rem;
  color: var(--on-surface);
  font-family: var(--font-display);
  letter-spacing: 0.18em;
  padding: 0.25rem 0;
  text-transform: uppercase;
  background: transparent;
  border: none;
  outline: none;
}

.gift-card-balance-strip__input::placeholder {
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.18em;
}

.gift-card-balance-strip__input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Keyboard focus indicators (WCAG 2.4.7) — design-system gold double-ring,
   replacing the stripped outline. */
.gift-card-balance-strip__input:focus-visible,
.gift-card-balance-strip__btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 0.125rem var(--surface), 0 0 0 0.25rem var(--secondary);
  border-radius: var(--radius-sm);
}

.gift-card-balance-strip__btn {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--secondary);
  padding: 0.25rem 0.5rem;
  border-left: 1px solid rgba(87, 66, 62, 0.4);
  background: transparent;
  border-top: none;
  border-right: none;
  border-bottom: none;
  cursor: pointer;
  font-family: var(--font-body);
  transition: color var(--duration-micro) var(--ease-standard);
}

.gift-card-balance-strip__btn:hover:not(:disabled) {
  color: var(--on-surface);
}

.gift-card-balance-strip__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.gift-card-balance-strip__result {
  color: var(--secondary);
  font-family: var(--font-display);
  letter-spacing: 0.04em;
  text-transform: none;
  font-size: 0.8125rem;
}

.gift-card-balance-strip__result--error {
  color: var(--state-danger-text, var(--primary));
}

@media (max-width: 64rem) {
  .gift-card-balance-strip__input {
    width: 8rem;
  }
}

@media (max-width: 40rem) {
  .gift-card-balance-strip {
    padding: 0 var(--space-md);
    font-size: 0.5625rem;
    gap: var(--space-md);
  }
  .gift-card-balance-strip__lede {
    display: none;
  }
}
</style>
