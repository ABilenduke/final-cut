<script setup lang="ts">
const { toasts } = useToast()
</script>

<template>
  <ClientOnly>
    <Teleport to="body">
      <div class="cv-toast-container" aria-label="Notifications" role="region">
        <TransitionGroup name="cv-toast-slide" tag="div">
          <CvToast
            v-for="toast in toasts"
            :key="toast.id"
            :toast="toast"
          />
        </TransitionGroup>
      </div>
    </Teleport>
  </ClientOnly>
</template>

<style scoped>
.cv-toast-container {
  position: fixed;
  bottom: var(--space-lg);
  right: var(--space-lg);
  z-index: var(--z-toast);
  pointer-events: none;
}

.cv-toast-container > :deep(div) {
  display: flex;
  flex-direction: column-reverse;
  gap: var(--space-sm);
}

.cv-toast-container :deep(.cv-toast) {
  pointer-events: auto;
}

/* Slide animation */
.cv-toast-slide-enter-active {
  transition:
    opacity var(--duration-standard) var(--ease-enter),
    transform var(--duration-standard) var(--ease-enter);
}

.cv-toast-slide-leave-active {
  transition:
    opacity var(--duration-standard) var(--ease-exit),
    transform var(--duration-standard) var(--ease-exit);
}

.cv-toast-slide-enter-from {
  opacity: 0;
  transform: translateY(100%); /* token-exception: entrance animation offset */
}

.cv-toast-slide-leave-to {
  opacity: 0;
  transform: translateY(100%); /* token-exception: exit animation offset */
}

.cv-toast-slide-move {
  transition: transform var(--duration-standard) var(--ease-standard);
}

@media (max-width: 39.999rem) {
  .cv-toast-container {
    left: var(--space-md);
    right: var(--space-md);
    bottom: var(--space-md);
  }
}
</style>
