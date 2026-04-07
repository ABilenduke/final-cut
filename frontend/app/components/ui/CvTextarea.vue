<script setup lang="ts">
import CvFormField from './_internal/CvFormField.vue'

const props = withDefaults(defineProps<{
  modelValue: string
  label: string
  placeholder?: string
  error?: string
  helpText?: string
  rows?: number
  disabled?: boolean
  required?: boolean
}>(), {
  rows: 4,
  disabled: false,
  required: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'focus': [event: FocusEvent]
  'blur': [event: FocusEvent]
}>()

defineOptions({ inheritAttrs: false })

function onInput(event: Event) {
  const target = event.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <CvFormField
    :label="label"
    :error="error"
    :help-text="helpText"
    :required="required"
    :disabled="disabled"
  >
    <template #default="{ id, ariaDescribedby }">
      <textarea
        :id="id"
        v-bind="$attrs"
        :value="modelValue"
        :placeholder="placeholder"
        :rows="rows"
        :disabled="disabled"
        :required="required"
        :aria-invalid="error ? true : undefined"
        :aria-describedby="ariaDescribedby"
        :aria-required="required || undefined"
        class="cv-textarea"
        :class="{ 'cv-textarea--error': error }"
        @input="onInput"
        @focus="emit('focus', $event)"
        @blur="emit('blur', $event)"
      />
    </template>
  </CvFormField>
</template>

<style scoped>
.cv-textarea {
  width: 100%;
  padding: var(--space-sm) 0;
  background: transparent;
  border: none;
  border-bottom: 0.0625rem solid var(--outline); /* token-exception: sub-pixel underline */
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
  line-height: 1.6;
  resize: vertical;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    box-shadow var(--duration-standard) var(--ease-standard);
}

.cv-textarea::placeholder {
  color: var(--on-tertiary-fixed-variant);
}

.cv-textarea:focus-visible {
  outline: none;
  border-bottom-color: var(--secondary);
  box-shadow: 0 0.125rem 0.5rem rgba(218, 199, 105, 0.3); /* token-exception: gold glow shadow */
}

.cv-textarea--error {
  border-bottom-color: var(--primary);
}

.cv-textarea:disabled {
  cursor: not-allowed;
}
</style>
