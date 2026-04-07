<script setup lang="ts">
const props = defineProps<{
  label: string
  error?: string
  helpText?: string
  required?: boolean
  disabled?: boolean
  /** Checkbox uses inline layout (label right of input) */
  inline?: boolean
}>()

const id = useId()!
const errorId = `${id}-error`
const helpId = `${id}-help`

const ariaDescribedby = computed(() => {
  const parts: string[] = []
  if (props.helpText && !props.error) parts.push(helpId)
  if (props.error) parts.push(errorId)
  return parts.length > 0 ? parts.join(' ') : undefined
})
</script>

<template>
  <div
    class="cv-form-field"
    :class="{ 'cv-form-field--inline': inline, 'cv-form-field--disabled': disabled }"
  >
    <label
      v-if="!inline"
      :for="id"
      class="cv-form-field__label"
    >
      {{ label }}
      <span v-if="required" class="cv-form-field__required" aria-hidden="true">*</span>
    </label>

    <slot :id="id" :aria-describedby="ariaDescribedby" />

    <label
      v-if="inline"
      :for="id"
      class="cv-form-field__label cv-form-field__label--inline"
    >
      {{ label }}
      <span v-if="required" class="cv-form-field__required" aria-hidden="true">*</span>
    </label>

    <p
      v-if="helpText && !error"
      :id="helpId"
      class="cv-form-field__help"
    >
      {{ helpText }}
    </p>

    <p
      v-if="error"
      :id="errorId"
      class="cv-form-field__error"
      aria-live="polite"
    >
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.cv-form-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.cv-form-field--inline {
  flex-direction: row;
  align-items: center;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.cv-form-field--disabled {
  opacity: 0.5; /* token-exception: component-specific opacity */
}

.cv-form-field__label {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
  line-height: 1.4;
}

.cv-form-field__label--inline {
  order: 1;
  cursor: pointer;
  font-size: var(--type-body-md);
  color: var(--on-surface);
}

.cv-form-field__required {
  color: var(--primary);
  margin-inline-start: var(--space-2xs);
}

.cv-form-field__help {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--on-tertiary-fixed-variant);
  line-height: 1.4;
  margin: 0;
}

.cv-form-field__error {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--primary);
  line-height: 1.4;
  margin: 0;
}

.cv-form-field--inline .cv-form-field__help,
.cv-form-field--inline .cv-form-field__error {
  flex-basis: 100%;
}
</style>
