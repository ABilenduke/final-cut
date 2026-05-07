<script setup lang="ts">
import { onMounted, onBeforeUnmount, watch, ref } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'

const props = defineProps<{
  open: boolean
  selectedDate: string
  events: CalendarEvent[]
}>()

const emit = defineEmits<{
  close: []
}>()

const panelRef = ref<HTMLElement | null>(null)
const focusTrap = useFocusTrap()
let trapActive = false
let priorBodyOverflow: string | null = null

function close() {
  emit('close')
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.open) {
    event.preventDefault()
    close()
  }
}

function lockBodyScroll() {
  if (typeof document === 'undefined') return
  if (priorBodyOverflow !== null) return
  priorBodyOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
}

function unlockBodyScroll() {
  if (typeof document === 'undefined') return
  if (priorBodyOverflow === null) return
  document.body.style.overflow = priorBodyOverflow
  priorBodyOverflow = null
}

function activateTrap() {
  if (trapActive || !panelRef.value) return
  focusTrap.activate(panelRef.value)
  trapActive = true
}

function deactivateTrap() {
  if (!trapActive) return
  focusTrap.deactivate()
  trapActive = false
}

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    lockBodyScroll()
    // Defer the trap activation until the panel ref is wired and the
    // teleport has flushed; useFocusTrap moves focus + sets `inert` on
    // siblings.
    requestAnimationFrame(activateTrap)
  } else {
    deactivateTrap()
    unlockBodyScroll()
  }
})

onMounted(() => {
  if (typeof document !== 'undefined') {
    document.addEventListener('keydown', onKeydown)
  }
})

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') {
    document.removeEventListener('keydown', onKeydown)
  }
  deactivateTrap()
  unlockBodyScroll()
})
</script>

<template>
  <Teleport to="body">
    <Transition name="bridge-drawer">
      <div
        v-if="open"
        class="bridge-drawer"
        role="dialog"
        aria-modal="true"
        aria-label="Selected day details"
      >
        <button
          type="button"
          class="bridge-drawer__backdrop"
          aria-label="Close detail drawer"
          @click="close"
        />
        <section ref="panelRef" class="bridge-drawer__panel" tabindex="-1">
          <header class="bridge-drawer__head">
            <span class="bridge-drawer__handle" aria-hidden="true" />
            <button
              type="button"
              class="bridge-drawer__close"
              aria-label="Close"
              @click="close"
            >
              <CvIcon name="close" size="md" />
            </button>
          </header>
          <div class="bridge-drawer__body">
            <BridgeDetailContent :selected-date="selectedDate" :events="events" />
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.bridge-drawer {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

.bridge-drawer__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background-color: rgba(0, 0, 0, 0.6);
  cursor: pointer;
}

.bridge-drawer__panel {
  position: relative;
  background-color: var(--surface-container);
  max-height: 90vh;
  border-radius: var(--radius-card) var(--radius-card) 0 0;
  display: flex;
  flex-direction: column;
  outline: none;
}

.bridge-drawer__head {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-md);
  position: sticky;
  top: 0;
  background-color: var(--surface-container);
  z-index: 1;
}

.bridge-drawer__handle {
  width: 2.5rem;
  height: 0.25rem;
  background-color: var(--outline-variant);
  border-radius: var(--radius-sm);
  opacity: 0.6;
}

.bridge-drawer__close {
  position: absolute;
  right: var(--space-md);
  top: 50%;
  transform: translateY(-50%);
  width: 2.25rem;
  height: 2.25rem;
  display: grid;
  place-items: center;
  background-color: var(--surface-container-low);
  color: var(--tertiary);
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.bridge-drawer__close:hover {
  background-color: var(--surface-container-high);
  color: var(--secondary);
}

.bridge-drawer__close:focus-visible {
  outline: var(--border-hairline) solid var(--secondary);
  outline-offset: 0.125rem;
}

.bridge-drawer__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: 0 var(--space-md) var(--space-lg);
  overflow-y: auto;
}

.bridge-drawer-enter-from,
.bridge-drawer-leave-to {
  opacity: 0;
}

.bridge-drawer-enter-from .bridge-drawer__panel,
.bridge-drawer-leave-to .bridge-drawer__panel {
  transform: translateY(100%);
}

.bridge-drawer-enter-active,
.bridge-drawer-leave-active {
  transition: opacity var(--duration-emphasis) var(--ease-standard);
}

.bridge-drawer-enter-active .bridge-drawer__panel,
.bridge-drawer-leave-active .bridge-drawer__panel {
  transition: transform var(--duration-emphasis) var(--ease-emphasis);
}

@media (prefers-reduced-motion: reduce) {
  .bridge-drawer-enter-active,
  .bridge-drawer-leave-active,
  .bridge-drawer-enter-active .bridge-drawer__panel,
  .bridge-drawer-leave-active .bridge-drawer__panel {
    transition: none;
  }
}

@media (min-width: 80rem) {
  .bridge-drawer {
    display: none;
  }
}
</style>
