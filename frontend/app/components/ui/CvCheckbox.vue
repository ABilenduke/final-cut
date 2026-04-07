<script setup lang="ts">
import CvFormField from './_internal/CvFormField.vue'

const props = withDefaults(defineProps<{
  modelValue: boolean
  label: string
  error?: string
  helpText?: string
  disabled?: boolean
  required?: boolean
}>(), {
  disabled: false,
  required: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

defineOptions({ inheritAttrs: false })

function onToggle() {
  if (props.disabled) return
  emit('update:modelValue', !props.modelValue)
}
</script>

<template>
  <CvFormField
    :label="label"
    :error="error"
    :help-text="helpText"
    :required="required"
    :disabled="disabled"
    inline
  >
    <template #default="{ id, ariaDescribedby }">
      <div class="cv-checkbox" :class="{ 'cv-checkbox--checked': modelValue, 'cv-checkbox--disabled': disabled }">
        <input
          :id="id"
          v-bind="$attrs"
          type="checkbox"
          :checked="modelValue"
          :disabled="disabled"
          :required="required"
          :aria-invalid="error ? true : undefined"
          :aria-describedby="ariaDescribedby"
          :aria-required="required || undefined"
          class="cv-checkbox__input"
          @change="onToggle"
        >
        <span class="cv-checkbox__indicator" aria-hidden="true">
          <svg
            v-if="modelValue"
            width="var(--icon-sm)"
            height="var(--icon-sm)"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
          </svg>
        </span>
      </div>
    </template>
  </CvFormField>
</template>

<style scoped>
.cv-checkbox {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: var(--space-lg);
  height: var(--space-lg);
  flex-shrink: 0;
}

.cv-checkbox__input {
  position: absolute;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0; /* token-exception: visually hidden native input */
  cursor: pointer;
}

.cv-checkbox--disabled .cv-checkbox__input {
  cursor: not-allowed;
}

.cv-checkbox__indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background-color: var(--surface-container-high);
  border: 0.0625rem solid var(--outline); /* token-exception: sub-pixel checkbox border */
  border-radius: 0.125rem; /* token-exception: design system radius */
  color: var(--primary);
  pointer-events: none;
  transition:
    background-color var(--duration-micro) var(--ease-standard),
    border-color var(--duration-micro) var(--ease-standard);
}

.cv-checkbox__indicator svg {
  width: var(--icon-sm);
  height: var(--icon-sm);
}

.cv-checkbox--checked .cv-checkbox__indicator {
  background-color: var(--primary-container);
  border-color: var(--primary-container);
}

/* Focus ring on the visual indicator when native input is focused */
.cv-checkbox__input:focus-visible + .cv-checkbox__indicator {
  outline: 0.125rem solid var(--secondary); /* token-exception: radius for focus ring */
  outline-offset: 0.125rem;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}
</style>
