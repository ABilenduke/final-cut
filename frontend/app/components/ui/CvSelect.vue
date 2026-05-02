<script setup lang="ts">
import CvFormField from './_internal/CvFormField.vue'

const props = withDefaults(defineProps<{
  modelValue: string | number
  options: Array<{ value: string | number; label: string }>
  label: string
  placeholder?: string
  error?: string
  helpText?: string
  disabled?: boolean
  required?: boolean
}>(), {
  placeholder: 'Select\u2026',
  disabled: false,
  required: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()

defineOptions({ inheritAttrs: false })

function onChange(event: Event) {
  const target = event.target as HTMLSelectElement
  const domValue = target.value
  // Preserve the original typed value from the matching option
  const matched = props.options.find((opt) => String(opt.value) === domValue)
  emit('update:modelValue', matched ? matched.value : domValue)
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
      <select
        :id="id"
        v-bind="$attrs"
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :aria-invalid="error ? true : undefined"
        :aria-describedby="ariaDescribedby"
        :aria-required="required || undefined"
        class="cv-select"
        :class="{ 'cv-select--error': error }"
        @change="onChange"
      >
        <option v-if="modelValue === '' || modelValue === null || modelValue === undefined" value="" disabled hidden>
          {{ placeholder }}
        </option>
        <option
          v-for="opt in options"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>
    </template>
  </CvFormField>
</template>

<style scoped>
.cv-select {
  width: 100%;
  height: 3rem;
  padding: var(--space-sm) var(--space-lg) var(--space-sm) 0;
  background-color: transparent;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23CCC6B6'%3E%3Cpath d='M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0 center;
  background-size: var(--icon-md);
  border: none;
  border-bottom: var(--border-hairline) solid var(--outline);
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--on-surface);
  line-height: 1.6;
  appearance: none;
  cursor: pointer;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    box-shadow var(--duration-standard) var(--ease-standard);
}

.cv-select:focus-visible {
  outline: none;
  border-bottom-color: var(--secondary);
  box-shadow: 0 0.125rem 0.5rem rgb(var(--secondary-rgb) / 0.3); /* token-exception: gold glow shadow */
}

.cv-select--error {
  border-bottom-color: var(--primary-container);
}

.cv-select:disabled {
  cursor: not-allowed;
}

/* Style the option dropdown */
.cv-select option {
  background-color: var(--surface-container-high);
  color: var(--on-surface);
}
</style>
