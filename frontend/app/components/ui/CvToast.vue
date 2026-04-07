<script setup lang="ts">
import type { Toast } from '~/composables/useToast'

const props = defineProps<{
  toast: Toast
}>()

const emit = defineEmits<{
  dismiss: []
}>()

const { dismiss } = useToast()

// Auto-dismiss timer with pause on hover/focus
let timerId: ReturnType<typeof setTimeout> | null = null
let remainingTime = props.toast.duration
let startTime = 0
let isHovered = false
let isFocused = false

function startTimer() {
  if (props.toast.duration <= 0 || timerId) return
  startTime = Date.now()
  timerId = setTimeout(() => {
    dismiss(props.toast.id)
    emit('dismiss')
  }, remainingTime)
}

function pauseTimer() {
  if (timerId) {
    clearTimeout(timerId)
    timerId = null
    remainingTime -= Date.now() - startTime
    if (remainingTime < 0) remainingTime = 0
  }
}

function tryResume() {
  if (!isHovered && !isFocused) {
    startTimer()
  }
}

function onMouseEnter() {
  isHovered = true
  pauseTimer()
}

function onMouseLeave() {
  isHovered = false
  tryResume()
}

function onFocusIn() {
  isFocused = true
  pauseTimer()
}

function onFocusOut() {
  isFocused = false
  tryResume()
}

onMounted(() => {
  startTimer()
})

onUnmounted(() => {
  if (timerId) {
    clearTimeout(timerId)
  }
})

function handleDismiss() {
  dismiss(props.toast.id)
  emit('dismiss')
}

const ariaRole = computed(() => props.toast.type === 'error' ? 'alert' : 'status')
const ariaLive = computed(() => props.toast.type === 'error' ? 'assertive' : 'polite')
</script>

<template>
  <div
    class="cv-toast"
    :class="`cv-toast--${toast.type}`"
    :role="ariaRole"
    :aria-live="ariaLive"
    @mouseenter="onMouseEnter"
    @mouseleave="onMouseLeave"
    @focusin="onFocusIn"
    @focusout="onFocusOut"
  >
    <p class="cv-toast__message">{{ toast.message }}</p>
    <button
      type="button"
      class="cv-toast__dismiss"
      aria-label="Dismiss notification"
      @click="handleDismiss"
    >
      <svg width="var(--icon-sm)" height="var(--icon-sm)" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
      </svg>
    </button>
  </div>
</template>

<style scoped>
.cv-toast {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  background-color: var(--surface-container-high);
  border-radius: 0.125rem; /* token-exception: design system radius */
  box-shadow: 0 1.25rem 2.5rem rgba(0, 0, 0, 0.6); /* token-exception: floating shadow */
  min-width: 15rem;
  max-width: 25rem;
}

.cv-toast--success {
  border-left: 0.1875rem solid var(--secondary); /* token-exception: accent border width */
}

.cv-toast--error {
  border-left: 0.1875rem solid var(--primary-container); /* token-exception: accent border width */
}

.cv-toast__message {
  flex: 1;
  margin: 0;
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--on-surface);
  line-height: 1.6;
}

.cv-toast__dismiss {
  display: flex;
  align-items: center;
  justify-content: center;
  width: var(--space-lg);
  height: var(--space-lg);
  flex-shrink: 0;
  background: none;
  border: none;
  color: var(--tertiary);
  cursor: pointer;
  border-radius: 0.125rem; /* token-exception: design system radius */
}

.cv-toast__dismiss svg {
  width: var(--icon-sm);
  height: var(--icon-sm);
}

.cv-toast__dismiss:hover {
  color: var(--on-surface);
}
</style>
