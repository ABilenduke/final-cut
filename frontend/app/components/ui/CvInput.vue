<script setup lang="ts">
import CvFormField from './_internal/CvFormField.vue'

const props = withDefaults(defineProps<{
  modelValue: string | number
  type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'search'
  label: string
  placeholder?: string
  error?: string
  helpText?: string
  disabled?: boolean
  required?: boolean
  size?: 'sm' | 'default'
}>(), {
  type: 'text',
  disabled: false,
  required: false,
  size: 'default',
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
  'focus': [event: FocusEvent]
  'blur': [event: FocusEvent]
}>()

defineOptions({ inheritAttrs: false })

function onInput(event: Event) {
  const target = event.target as HTMLInputElement
  if (props.type === 'number') {
    emit('update:modelValue', target.value === '' ? '' : Number(target.value))
  } else {
    emit('update:modelValue', target.value)
  }
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
      <input
        :id="id"
        v-bind="$attrs"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :aria-invalid="error ? true : undefined"
        :aria-describedby="ariaDescribedby"
        :aria-required="required || undefined"
        class="cv-input"
        :class="[
          `cv-input--${size}`,
          { 'cv-input--error': error },
        ]"
        @input="onInput"
        @focus="emit('focus', $event)"
        @blur="emit('blur', $event)"
      >
    </template>
  </CvFormField>
</template>

<style scoped>
.cv-input {
  width: 100%;
  height: 3rem;
  padding: var(--space-sm) 0;
  background: transparent;
  border: none;
  border-bottom: 0.0625rem solid var(--outline); /* token-exception: sub-pixel underline */
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
  line-height: 1.6;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    box-shadow var(--duration-standard) var(--ease-standard);
}

.cv-input::placeholder {
  color: var(--on-tertiary-fixed-variant);
}

.cv-input:focus-visible {
  outline: none;
  border-bottom-color: var(--secondary);
  box-shadow: 0 0.125rem 0.5rem rgba(218, 199, 105, 0.3); /* token-exception: gold glow shadow */
}

.cv-input--error {
  border-bottom-color: var(--primary);
}

.cv-input:disabled {
  cursor: not-allowed;
}

@media (pointer: fine) {
  .cv-input--sm {
    height: 2.25rem;
    font-size: var(--type-body-sm);
  }
}
</style>
