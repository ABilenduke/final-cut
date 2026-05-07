<script setup lang="ts">
import { ref } from 'vue'

interface SegmentOption {
  value: string
  label: string
  disabled?: boolean
  hint?: string
}

const props = withDefaults(defineProps<{
  modelValue: string
  options: SegmentOption[]
  label?: string
}>(), {})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const buttons = ref<HTMLButtonElement[]>([])

function select(value: string) {
  const opt = props.options.find((o) => o.value === value)
  if (!opt || opt.disabled) return
  if (value === props.modelValue) return
  emit('update:modelValue', value)
}

function focusButton(index: number) {
  const btn = buttons.value[index]
  if (btn) btn.focus()
}

function onKeydown(event: KeyboardEvent, index: number) {
  if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return
  event.preventDefault()
  const direction = event.key === 'ArrowRight' ? 1 : -1
  const total = props.options.length
  let next = index
  for (let i = 0; i < total; i += 1) {
    next = (next + direction + total) % total
    const opt = props.options[next]
    if (opt && !opt.disabled) {
      focusButton(next)
      select(opt.value)
      return
    }
  }
}
</script>

<template>
  <div
    class="cv-segmented"
    role="tablist"
    :aria-label="label"
  >
    <button
      v-for="(option, index) in options"
      :key="option.value"
      :ref="(el) => { if (el) buttons[index] = el as HTMLButtonElement }"
      type="button"
      role="tab"
      class="cv-segmented__btn"
      :class="{
        'cv-segmented__btn--active': option.value === modelValue,
        'cv-segmented__btn--disabled': option.disabled,
      }"
      :aria-selected="option.value === modelValue"
      :aria-disabled="option.disabled || undefined"
      :disabled="option.disabled || undefined"
      :tabindex="option.value === modelValue ? 0 : -1"
      :title="option.hint"
      @click="select(option.value)"
      @keydown="(e) => onKeydown(e, index)"
    >
      {{ option.label }}
    </button>
  </div>
</template>

<style scoped>
.cv-segmented {
  display: inline-flex;
  background-color: var(--surface-container-low);
  padding: 0.25rem;
  border-radius: var(--radius-sm);
  gap: 0.125rem;
}

.cv-segmented__btn {
  padding: 0.4rem 0.85rem;
  border: none;
  border-radius: 0.125rem;
  background: transparent;
  color: var(--on-tertiary-fixed-variant);
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  letter-spacing: 0.16em;
  text-transform: uppercase;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.cv-segmented__btn:hover:not(.cv-segmented__btn--disabled):not(.cv-segmented__btn--active) {
  color: var(--on-surface);
}

.cv-segmented__btn--active {
  background-color: var(--primary-container);
  color: var(--primary);
}

.cv-segmented__btn--disabled {
  opacity: 0.4; /* token-exception: component-specific opacity */
  cursor: not-allowed;
}

.cv-segmented__btn:focus-visible {
  outline: var(--border-hairline) solid var(--secondary);
  outline-offset: 0.125rem;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}
</style>
