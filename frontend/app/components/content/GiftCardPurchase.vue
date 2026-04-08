<script setup lang="ts">
const emit = defineEmits<{
  'purchase': [payload: { amount: number; recipientEmail: string; recipientName: string; senderName: string; message: string }]
}>()

const presetAmounts = [2500, 5000, 7500, 10000]

const selectedPreset = ref<number | null>(5000)
const customAmount = ref('')
const useCustom = ref(false)

const recipientName = ref('')
const recipientEmail = ref('')
const senderName = ref('')
const message = ref('')
const loading = ref(false)

const errors = ref<Record<string, string>>({})

const amount = computed(() => {
  if (useCustom.value) {
    const cents = Math.round(Number(customAmount.value) * 100)
    return Number.isFinite(cents) ? cents : 0
  }
  return selectedPreset.value ?? 0
})

function selectPreset(value: number) {
  selectedPreset.value = value
  useCustom.value = false
  customAmount.value = ''
  delete errors.value.amount
}

function enableCustom() {
  useCustom.value = true
  selectedPreset.value = null
}

function validate(): boolean {
  const e: Record<string, string> = {}
  if (amount.value < 500) e.amount = 'Minimum amount is $5.00'
  if (amount.value > 50000) e.amount = 'Maximum amount is $500.00'
  if (!recipientName.value.trim()) e.recipientName = 'Recipient name is required'
  if (!recipientEmail.value.trim()) {
    e.recipientEmail = 'Recipient email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipientEmail.value)) {
    e.recipientEmail = 'Please enter a valid email'
  }
  if (!senderName.value.trim()) e.senderName = 'Your name is required'
  errors.value = e
  return Object.keys(e).length === 0
}

async function handleSubmit() {
  if (!validate()) return

  loading.value = true
  try {
    const payload = {
      amount: amount.value,
      recipientEmail: recipientEmail.value.trim(),
      recipientName: recipientName.value.trim(),
      senderName: senderName.value.trim(),
      message: message.value.trim(),
    }
    emit('purchase', payload)
    useToast().show({ message: 'Payment integration coming soon. Gift card form is ready.', type: 'info' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="gift-card-purchase" @submit.prevent="handleSubmit" novalidate>
    <h3 class="gift-card-purchase__heading headline-sm">Send a Gift Card</h3>

    <div class="gift-card-purchase__section">
      <p class="gift-card-purchase__label label-lg">Select Amount</p>
      <div class="gift-card-purchase__amounts">
        <button
          v-for="value in presetAmounts"
          :key="value"
          type="button"
          class="gift-card-purchase__preset"
          :class="{ 'gift-card-purchase__preset--active': !useCustom && selectedPreset === value }"
          @click="selectPreset(value)"
        >
          {{ formatCurrency(value) }}
        </button>
        <button
          type="button"
          class="gift-card-purchase__preset"
          :class="{ 'gift-card-purchase__preset--active': useCustom }"
          @click="enableCustom"
        >
          Custom
        </button>
      </div>
      <CvInput
        v-if="useCustom"
        v-model="customAmount"
        label="Custom Amount ($)"
        type="number"
        placeholder="25.00"
        :error="errors.amount"
      />
      <p v-if="errors.amount && !useCustom" class="gift-card-purchase__error label-md">{{ errors.amount }}</p>
    </div>

    <div class="gift-card-purchase__section">
      <CvInput
        v-model="recipientName"
        label="Recipient Name"
        :error="errors.recipientName"
        required
      />
      <CvInput
        v-model="recipientEmail"
        label="Recipient Email"
        type="email"
        :error="errors.recipientEmail"
        required
      />
      <CvInput
        v-model="senderName"
        label="Your Name"
        :error="errors.senderName"
        required
      />
      <CvTextarea
        v-model="message"
        label="Personal Message (optional)"
        :rows="3"
        placeholder="Enjoy the movies!"
      />
    </div>

    <CvButton
      type="submit"
      :loading="loading"
      :disabled="loading"
    >
      Purchase Gift Card
    </CvButton>
  </form>
</template>

<style scoped>
.gift-card-purchase {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.gift-card-purchase__heading {
  color: var(--on-surface);
  margin: 0;
}

.gift-card-purchase__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.gift-card-purchase__label {
  color: var(--tertiary);
  margin: 0;
}

.gift-card-purchase__amounts {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-sm);
}

.gift-card-purchase__preset {
  padding: var(--space-sm) var(--space-md);
  border: none;
  border-radius: 0.125rem;
  background-color: var(--surface-container-high);
  color: var(--tertiary);
  font-family: var(--font-body);
  font-size: var(--type-label-lg);
  cursor: pointer;
  transition: background-color var(--duration-micro) var(--ease-standard),
              color var(--duration-micro) var(--ease-standard);
  min-height: 3rem;
}

.gift-card-purchase__preset--active {
  background-color: var(--primary-container);
  color: var(--primary);
}

.gift-card-purchase__preset:not(.gift-card-purchase__preset--active):hover {
  color: var(--on-surface);
}

.gift-card-purchase__error {
  color: var(--primary);
  margin: 0;
}
</style>
