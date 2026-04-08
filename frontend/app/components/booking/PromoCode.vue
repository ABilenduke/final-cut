<script setup lang="ts">
const props = defineProps<{
  appliedCode: string | null
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
  emit('apply', code)
}

function handleRemove() {
  codeInput.value = ''
  error.value = ''
  emit('remove')
}
</script>

<template>
  <div class="promo-code">
    <template v-if="appliedCode">
      <div class="promo-code__applied">
        <span class="promo-code__code">{{ appliedCode }}</span>
        <button
          type="button"
          class="promo-code__remove"
          @click="handleRemove"
        >
          Remove
        </button>
      </div>
    </template>
    <template v-else>
      <div class="promo-code__form">
        <CvInput
          v-model="codeInput"
          label="Promo Code"
          placeholder="Enter code"
          :error="error"
          size="sm"
          @keydown.enter="handleApply"
        />
        <CvButton
          variant="secondary"
          size="sm"
          @click="handleApply"
        >
          Apply
        </CvButton>
      </div>
    </template>
  </div>
</template>

<style scoped>
.promo-code__form {
  display: flex;
  align-items: flex-start;
  gap: var(--space-sm);
}

.promo-code__form :deep(.cv-form-field) {
  flex: 1;
}

.promo-code__applied {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-sm);
  background-color: var(--surface-container-low);
  border-radius: 0.125rem;
}

.promo-code__code {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--secondary);
  flex: 1;
}

.promo-code__remove {
  background: none;
  border: none;
  cursor: pointer;
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--primary);
  padding: var(--space-xs);
}

.promo-code__remove:hover {
  text-decoration: underline;
}
</style>
