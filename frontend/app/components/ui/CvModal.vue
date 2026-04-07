<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  size?: 'sm' | 'default' | 'lg'
  closeable?: boolean
}>(), {
  size: 'default',
  closeable: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const panelRef = ref<HTMLElement | null>(null)
const titleId = useId()!
const focusTrap = useFocusTrap()

const sizeMaxWidth: Record<string, string> = {
  sm: '25rem',
  default: '35rem',
  lg: '50rem',
}

function close() {
  if (props.closeable) {
    emit('update:modelValue', false)
  }
}

function onBackdropClick(event: MouseEvent) {
  // Only close if clicking the backdrop itself, not the panel
  if (event.target === event.currentTarget) {
    close()
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.closeable) {
    close()
  }
}

watch(() => props.modelValue, (open) => {
  if (open) {
    nextTick(() => {
      if (panelRef.value) {
        focusTrap.activate(panelRef.value)
      }
      document.body.style.overflow = 'hidden'
      document.addEventListener('keydown', onKeydown)
    })
  } else {
    focusTrap.deactivate()
    document.body.style.overflow = ''
    document.removeEventListener('keydown', onKeydown)
  }
}, { immediate: true })

onUnmounted(() => {
  if (props.modelValue) {
    focusTrap.deactivate()
    document.body.style.overflow = ''
    document.removeEventListener('keydown', onKeydown)
  }
})
</script>

<template>
  <ClientOnly>
    <Teleport to="body">
      <Transition name="cv-modal">
        <div
          v-if="modelValue"
          class="cv-modal__backdrop"
          @click="onBackdropClick"
        >
          <div
            ref="panelRef"
            class="cv-modal__panel"
            :style="{ maxWidth: sizeMaxWidth[size] }"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
          >
            <div class="cv-modal__header">
              <h2 :id="titleId" class="cv-modal__title">{{ title }}</h2>
              <button
                v-if="closeable"
                type="button"
                class="cv-modal__close"
                aria-label="Close dialog"
                @click="close"
              >
                <svg width="var(--icon-md)" height="var(--icon-md)" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                </svg>
              </button>
            </div>
            <div class="cv-modal__body">
              <slot />
            </div>
            <div v-if="$slots.footer" class="cv-modal__footer">
              <slot name="footer" />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ClientOnly>
</template>

<style scoped>
.cv-modal__backdrop {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal-backdrop);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-md);

  /* Glassmorphism fallback: solid translucent */
  background-color: rgba(var(--surface-variant-rgb), 0.85);
}

@supports (backdrop-filter: blur(1px)) {
  .cv-modal__backdrop {
    background-color: rgba(var(--surface-variant-rgb), 0.6);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
  }
}

.cv-modal__panel {
  position: relative;
  z-index: var(--z-modal);
  width: 100%;
  max-height: calc(100vh - var(--space-2xl) * 2);
  overflow-y: auto;
  background-color: var(--surface-container-high);
  border-radius: 0.125rem; /* token-exception: design system radius */
  box-shadow: 0 1.25rem 2.5rem rgba(0, 0, 0, 0.6); /* token-exception: floating shadow tint */
}

.cv-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 3.5rem;
  padding: 0 var(--space-md);
}

.cv-modal__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--on-surface);
  margin: 0;
  letter-spacing: -0.02em;
}

.cv-modal__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: var(--space-xl);
  height: var(--space-xl);
  background: none;
  border: none;
  color: var(--tertiary);
  cursor: pointer;
  border-radius: 0.125rem; /* token-exception: design system radius */
}

.cv-modal__close svg {
  width: var(--icon-md);
  height: var(--icon-md);
}

.cv-modal__close:hover {
  color: var(--on-surface);
}

.cv-modal__body {
  padding: 0 var(--space-md) var(--space-md);
  color: var(--on-surface);
}

.cv-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md) var(--space-md);
}

/* Transitions */
.cv-modal-enter-active,
.cv-modal-leave-active {
  transition:
    opacity var(--duration-emphasis) var(--ease-enter),
    transform var(--duration-emphasis) var(--ease-enter);
}

.cv-modal-leave-active {
  transition-timing-function: var(--ease-exit);
}

.cv-modal-enter-from {
  opacity: 0;
}

.cv-modal-leave-to {
  opacity: 0;
}

.cv-modal-enter-from .cv-modal__panel {
  transform: scale(0.95); /* token-exception: entrance animation scale */
}
</style>
