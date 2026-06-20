<script setup lang="ts">
defineProps<{
  appliedCode: string | null
  discount?: number
}>()

const emit = defineEmits<{
  apply: [code: string]
  remove: []
}>()

const codeInput = ref('')
const error = ref('')

function handleApply() {
  const code = codeInput.value.trim()
  if (!code) {
    error.value = 'Please enter a promo code'
    return
  }
  error.value = ''
  codeInput.value = ''
  emit('apply', code)
}

function handleRemove() {
  error.value = ''
  emit('remove')
}
</script>

<template>
  <section class="bay bay--inset promo-bay">
    <header class="bay__header promo-bay__header">
      <div>
        <div class="bay__number">§ 04</div>
        <h2 class="bay__title">Promo <em>code.</em></h2>
      </div>
    </header>

    <label class="promo-bay__label">Promo code or member offer</label>

    <div class="promo-bay__row">
      <input
        v-model="codeInput"
        type="text"
        class="promo-bay__input"
        placeholder="e.g. REEL2026"
        :aria-invalid="Boolean(error)"
        @keydown.enter.prevent="handleApply"
      >
      <CvButton variant="secondary" @click="handleApply">
        Apply
      </CvButton>
    </div>
    <p v-if="error" class="promo-bay__error" role="alert">{{ error }}</p>

    <div v-if="appliedCode" class="promo-bay__applied">
      <span>
        <b>{{ appliedCode }}</b>
        <template v-if="(discount ?? 0) > 0">
          — {{ formatCurrency(discount ?? 0) }} discount applied
        </template>
        <template v-else>
          — will be validated at checkout
        </template>
      </span>
      <button type="button" class="promo-bay__remove" @click="handleRemove">Remove</button>
    </div>
  </section>
</template>

<style scoped>
.promo-bay__header {
  padding-bottom: 0;
  border-bottom: none;
  margin-bottom: var(--space-md);
}

.promo-bay__label {
  display: block;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-bottom: 0.4rem;
}

.promo-bay__row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: var(--space-sm);
}

.promo-bay__input {
  background-color: var(--surface-container-low);
  color: var(--on-surface);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
  border-radius: var(--radius-sm);
  padding: 0.85rem 0.95rem;
  font-family: var(--font-body);
  font-size: 1rem;
  width: 100%;
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.promo-bay__input::placeholder {
  color: var(--on-tertiary-fixed-variant);
  opacity: 0.6;
}

.promo-bay__input:focus-visible {
  outline: none;
  border-color: var(--secondary);
  background-color: var(--surface-container);
}

.promo-bay__error {
  margin: var(--space-xs) 0 0;
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--primary);
}

.promo-bay__applied {
  margin-top: var(--space-sm);
  padding: 0.65rem 0.85rem;
  background-color: rgb(var(--secondary-rgb) / 0.06);
  border: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.25);
  border-radius: var(--radius-sm);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-md);
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--on-surface);
}

.promo-bay__applied b {
  font-family: var(--font-display);
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.04em;
}

.promo-bay__remove {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--on-tertiary-fixed-variant);
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.promo-bay__remove:hover,
.promo-bay__remove:focus-visible {
  color: var(--secondary);
}
</style>
