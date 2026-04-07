<script setup lang="ts">
const props = withDefaults(defineProps<{
  title: string
  id: string
  open?: boolean
}>(), {
  open: undefined,
})

const emit = defineEmits<{
  'toggle': [open: boolean]
}>()

// Controlled/uncontrolled: if open prop is provided, use it; otherwise internal state
const internalOpen = ref(false)
const isOpen = computed(() => props.open !== undefined ? props.open : internalOpen.value)

const panelId = computed(() => `${props.id}-panel`)
const triggerId = computed(() => `${props.id}-trigger`)

function toggle() {
  const newState = !isOpen.value
  if (props.open === undefined) {
    internalOpen.value = newState
  }
  emit('toggle', newState)
}
</script>

<template>
  <div class="cv-accordion">
    <button
      :id="triggerId"
      type="button"
      class="cv-accordion__trigger"
      :aria-expanded="isOpen"
      :aria-controls="panelId"
      @click="toggle"
    >
      <span class="cv-accordion__title">{{ title }}</span>
      <svg
        class="cv-accordion__chevron"
        :class="{ 'cv-accordion__chevron--open': isOpen }"
        width="var(--icon-md)"
        height="var(--icon-md)"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
      >
        <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
      </svg>
    </button>
    <div
      :id="panelId"
      role="region"
      :aria-labelledby="triggerId"
      class="cv-accordion__panel"
      :class="{ 'cv-accordion__panel--open': isOpen }"
    >
      <div class="cv-accordion__content">
        <slot />
      </div>
    </div>
  </div>
</template>

<style scoped>
.cv-accordion {
  /* No divider lines — spacing separates items */
}

.cv-accordion__trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: var(--space-sm) 0;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  color: var(--on-surface);
}

.cv-accordion__title {
  font-family: var(--font-body);
  font-size: var(--type-body-lg);
  line-height: 1.6;
}

.cv-accordion__chevron {
  width: var(--icon-md);
  height: var(--icon-md);
  flex-shrink: 0;
  color: var(--tertiary);
  transition: transform var(--duration-standard) var(--ease-standard);
}

.cv-accordion__chevron--open {
  transform: rotate(180deg); /* token-exception: component-specific rotation */
}

.cv-accordion__panel {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows var(--duration-emphasis) var(--ease-enter);
}

.cv-accordion__panel--open {
  grid-template-rows: 1fr;
}

.cv-accordion__content {
  overflow: hidden;
  min-height: 0;
}

.cv-accordion__panel--open .cv-accordion__content {
  padding-bottom: var(--space-md);
}
</style>
