<script setup lang="ts">
</script>

<template>
  <div class="auditorium-legend" role="group" aria-label="Seat legend">
    <div class="auditorium-legend__item">
      <span class="auditorium-legend__swatch auditorium-legend__swatch--available" />
      <span class="auditorium-legend__label">Available</span>
    </div>
    <div class="auditorium-legend__item">
      <span class="auditorium-legend__swatch auditorium-legend__swatch--selected">
        <CvIcon name="check" size="sm" />
      </span>
      <span class="auditorium-legend__label">Selected</span>
    </div>
    <div class="auditorium-legend__item">
      <span class="auditorium-legend__swatch auditorium-legend__swatch--taken" />
      <span class="auditorium-legend__label">Taken</span>
    </div>
    <div class="auditorium-legend__item">
      <span class="auditorium-legend__swatch auditorium-legend__swatch--accessible">
        <CvIcon name="accessible" size="sm" />
      </span>
      <span class="auditorium-legend__label">Accessible</span>
    </div>
    <div class="auditorium-legend__item">
      <span class="auditorium-legend__swatch auditorium-legend__swatch--premium" />
      <span class="auditorium-legend__label">Premium</span>
    </div>
  </div>
</template>

<style scoped>
.auditorium-legend {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-lg);
  padding: var(--space-md) 0;
}

.auditorium-legend__item {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.auditorium-legend__swatch {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.125rem;
}

.auditorium-legend__swatch--available {
  background-color: var(--surface-container-high);
}

.auditorium-legend__swatch--selected {
  background-color: var(--primary-container);
  color: var(--primary);
}

.auditorium-legend__swatch--taken {
  background-color: var(--surface-container-low);
  opacity: 0.4;
}

.auditorium-legend__swatch--accessible {
  background-color: var(--surface-container-high);
  color: var(--secondary);
}

.auditorium-legend__swatch--premium {
  background-color: var(--surface-container-high);
  border-bottom: 0.125rem solid var(--secondary-container);
}

.auditorium-legend__label {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
}
</style>
