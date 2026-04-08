<script setup lang="ts">
const code = ref('')
const loading = ref(false)
const balance = ref<number | null>(null)
const status = ref<string | null>(null)
const error = ref('')

async function handleCheck() {
  if (!code.value.trim()) {
    error.value = 'Please enter a gift card code'
    return
  }

  error.value = ''
  balance.value = null
  status.value = null
  loading.value = true

  try {
    const result = await useGiftCards().checkBalance(code.value.trim())
    balance.value = result.data.balance
    status.value = result.data.status
  } catch {
    error.value = 'Could not find that gift card. Please check the code and try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="balance-checker">
    <h3 class="balance-checker__heading headline-sm">Check Balance</h3>
    <form class="balance-checker__form" @submit.prevent="handleCheck" novalidate>
      <CvInput
        v-model="code"
        label="Gift Card Code"
        placeholder="Enter your code"
        :error="error"
      />
      <CvButton
        type="submit"
        variant="secondary"
        :loading="loading"
        :disabled="loading"
      >
        Check Balance
      </CvButton>
    </form>

    <div v-if="balance !== null" class="balance-checker__result">
      <p class="balance-checker__balance display-sm">{{ formatCurrency(balance) }}</p>
      <CvBadge v-if="status" size="sm" :variant="status === 'active' ? 'default' : 'accent'">
        {{ status.charAt(0).toUpperCase() + status.slice(1) }}
      </CvBadge>
    </div>
  </div>
</template>

<style scoped>
.balance-checker {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.balance-checker__heading {
  color: var(--on-surface);
  margin: 0;
}

.balance-checker__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.balance-checker__result {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-lg);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
  text-align: center;
}

.balance-checker__balance {
  color: var(--secondary);
  margin: 0;
}
</style>
