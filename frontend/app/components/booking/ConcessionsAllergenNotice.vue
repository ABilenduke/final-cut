<script setup lang="ts">
// Static editorial notice — no props. Surfaces the kitchen + auditorium policies
// the concessions design calls out in its allergen strip.
</script>

<template>
  <aside class="allergen-notice" role="note">
    <span class="allergen-notice__icon" aria-hidden="true">!</span>
    <p class="allergen-notice__body">
      <b>Allergens &amp; auditorium policy.</b>
      All items prepared in a shared kitchen; traces of nuts and gluten may be present.
      Hot food stays in the lobby — drinks and dry snacks are welcome in the auditorium.
      We ask for no rustling wrappers during the feature presentation.
    </p>
  </aside>
</template>

<style scoped>
.allergen-notice {
  display: flex;
  gap: var(--space-md);
  padding: var(--space-md) var(--space-lg);
  background-color: var(--surface-container-low);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
  border-left: 0.125rem solid var(--secondary);
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  font-size: 0.8125rem;
  line-height: 1.55;
  color: var(--tertiary);
}

.allergen-notice__icon {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background-color: rgb(var(--secondary-rgb) / 0.1);
  display: grid;
  place-items: center;
  color: var(--secondary);
  flex-shrink: 0;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 1rem;
}

.allergen-notice__body {
  margin: 0;
}

.allergen-notice__body b {
  color: var(--on-surface);
  font-family: var(--font-display);
  font-weight: 500;
  letter-spacing: 0.01em;
}
</style>
