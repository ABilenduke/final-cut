<script setup lang="ts">
const props = defineProps<{
  categories: string[]
  active: string
}>()

const emit = defineEmits<{
  'select': [category: string]
}>()

const tabRefs = ref<HTMLButtonElement[]>([])

function formatLabel(category: string): string {
  return category.charAt(0).toUpperCase() + category.slice(1)
}

function handleKeydown(event: KeyboardEvent, index: number) {
  let nextIndex: number | null = null

  if (event.key === 'ArrowRight') {
    nextIndex = (index + 1) % props.categories.length
  } else if (event.key === 'ArrowLeft') {
    nextIndex = (index - 1 + props.categories.length) % props.categories.length
  } else if (event.key === 'Home') {
    nextIndex = 0
  } else if (event.key === 'End') {
    nextIndex = props.categories.length - 1
  }

  if (nextIndex !== null) {
    event.preventDefault()
    tabRefs.value[nextIndex]?.focus()
    emit('select', props.categories[nextIndex])
  }
}
</script>

<template>
  <div class="menu-tabs" role="tablist" aria-label="Menu categories">
    <button
      v-for="(cat, index) in categories"
      :key="cat"
      :ref="(el) => { if (el) tabRefs[index] = el as HTMLButtonElement }"
      role="tab"
      type="button"
      class="menu-tabs__tab"
      :class="{ 'menu-tabs__tab--active': active === cat }"
      :aria-selected="active === cat"
      :tabindex="active === cat ? 0 : -1"
      @click="emit('select', cat)"
      @keydown="handleKeydown($event, index)"
    >
      {{ formatLabel(cat) }}
    </button>
  </div>
</template>

<style scoped>
.menu-tabs {
  display: flex;
  gap: var(--space-sm);
  overflow-x: auto;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  padding-bottom: var(--space-xs);
}

.menu-tabs::-webkit-scrollbar {
  display: none;
}

.menu-tabs__tab {
  flex-shrink: 0;
  padding: var(--space-sm) var(--space-md);
  border: none;
  border-radius: 0.125rem;
  background-color: var(--surface-container-high);
  color: var(--tertiary);
  font-family: var(--font-body);
  font-size: var(--type-label-lg);
  cursor: pointer;
  transition: background-color var(--duration-micro) var(--ease-standard),
              color var(--duration-micro) var(--ease-standard);
  min-height: 2.25rem;
}

@media (pointer: coarse) {
  .menu-tabs__tab {
    min-height: 3rem;
  }
}

.menu-tabs__tab--active {
  background-color: var(--primary-container);
  color: var(--primary);
}

.menu-tabs__tab:not(.menu-tabs__tab--active):hover {
  color: var(--on-surface);
}
</style>
