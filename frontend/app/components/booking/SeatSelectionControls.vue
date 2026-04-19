<script setup lang="ts">
type Preference = 'together' | 'aisle' | 'center' | 'wheelchair'

const props = defineProps<{
  partySize: number
  preferences: Preference[]
  min?: number
  max?: number
}>()

const emit = defineEmits<{
  'update:partySize': [value: number]
  'update:preferences': [value: Preference[]]
  'auto-pick': []
}>()

const min = computed(() => props.min ?? 1)
const max = computed(() => props.max ?? 10)

function bumpParty(delta: number) {
  const next = Math.max(min.value, Math.min(max.value, props.partySize + delta))
  if (next !== props.partySize) emit('update:partySize', next)
}

function togglePreference(pref: Preference) {
  const set = new Set(props.preferences)
  if (set.has(pref)) set.delete(pref)
  else set.add(pref)
  emit('update:preferences', [...set])
}

const PREF_CHIPS: Array<{ id: Preference; label: string }> = [
  { id: 'together', label: 'Seat together' },
  { id: 'aisle', label: 'Aisle' },
  { id: 'center', label: 'Centre frame' },
  { id: 'wheelchair', label: 'Wheelchair' },
]
</script>

<template>
  <div class="seat-controls">
    <div class="seat-controls__group">
      <span class="seat-controls__label">Party</span>
      <div class="seat-controls__stepper">
        <button
          type="button"
          class="seat-controls__step-btn"
          aria-label="Decrease party size"
          :disabled="partySize <= min"
          @click="bumpParty(-1)"
        >−</button>
        <span class="seat-controls__step-n" aria-live="polite">{{ partySize }}</span>
        <button
          type="button"
          class="seat-controls__step-btn"
          aria-label="Increase party size"
          :disabled="partySize >= max"
          @click="bumpParty(1)"
        >+</button>
      </div>
      <span class="seat-controls__hint">
        adults
        <span>— edit types in your tray</span>
      </span>
    </div>

    <div class="seat-controls__group seat-controls__group--divider">
      <span class="seat-controls__label">Preferences</span>
      <div class="seat-controls__chips" role="group" aria-label="Seat preferences">
        <button
          v-for="chip in PREF_CHIPS"
          :key="chip.id"
          type="button"
          class="seat-controls__chip"
          :class="{ 'seat-controls__chip--on': preferences.includes(chip.id) }"
          :aria-pressed="preferences.includes(chip.id)"
          @click="togglePreference(chip.id)"
        >
          <span class="seat-controls__chip-mark" aria-hidden="true" />
          {{ chip.label }}
        </button>
      </div>
    </div>

    <div class="seat-controls__group seat-controls__group--push-right">
      <button
        type="button"
        class="seat-controls__auto"
        @click="emit('auto-pick')"
      >
        <span aria-hidden="true">◉</span> Pick for me
      </button>
    </div>
  </div>
</template>

<style scoped>
.seat-controls {
  display: flex;
  align-items: center;
  gap: var(--space-lg);
  flex-wrap: wrap;
  padding: var(--space-md) var(--space-lg);
  background-color: var(--surface-container-low);
  border: 0.0625rem solid rgba(87, 66, 62, 0.15);
  border-radius: 0.25rem;
}

.seat-controls__group {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  min-width: 0;
}

.seat-controls__group--divider {
  padding-left: var(--space-lg);
  border-left: 0.0625rem solid rgba(87, 66, 62, 0.25);
}

.seat-controls__group--push-right {
  margin-left: auto;
}

@media (max-width: 52rem) {
  .seat-controls__group--divider {
    padding-left: 0;
    border-left: none;
  }
  .seat-controls__group--push-right {
    margin-left: 0;
  }
}

.seat-controls__label {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.seat-controls__stepper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.seat-controls__step-btn {
  width: 1.8rem;
  height: 1.8rem;
  border: 0.0625rem solid rgba(87, 66, 62, 0.4);
  border-radius: 0.125rem;
  background: transparent;
  color: var(--on-surface);
  font: inherit;
  font-size: 1rem;
  line-height: 1;
  cursor: pointer;
  display: grid;
  place-items: center;
  transition: border-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard);
}

.seat-controls__step-btn:hover:not(:disabled),
.seat-controls__step-btn:focus-visible:not(:disabled) {
  border-color: var(--secondary);
  color: var(--secondary);
}

.seat-controls__step-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.seat-controls__step-n {
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--on-surface);
  min-width: 1.5rem;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.seat-controls__hint {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  line-height: 1.3;
}

.seat-controls__hint span {
  display: block;
  letter-spacing: 0.04em;
  text-transform: none;
  font-family: var(--font-display);
  color: var(--tertiary);
  font-size: 0.75rem;
}

.seat-controls__chips {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.seat-controls__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.75rem;
  border: 0.0625rem solid rgba(87, 66, 62, 0.35);
  border-radius: 999px;
  background-color: var(--surface-container);
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--tertiary);
  cursor: pointer;
  transition: color var(--duration-micro) var(--ease-standard),
    border-color var(--duration-micro) var(--ease-standard),
    background-color var(--duration-micro) var(--ease-standard);
}

.seat-controls__chip:hover,
.seat-controls__chip:focus-visible {
  border-color: rgba(218, 199, 105, 0.4);
  color: var(--on-surface);
}

.seat-controls__chip--on {
  border-color: var(--secondary);
  color: var(--secondary);
  background-color: rgba(218, 199, 105, 0.06);
}

.seat-controls__chip-mark {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  border: 0.0625rem solid currentColor;
}

.seat-controls__chip--on .seat-controls__chip-mark {
  background-color: currentColor;
}

.seat-controls__auto {
  padding: 0.55rem 0.9rem;
  border: 0.0625rem solid rgba(218, 199, 105, 0.4);
  background: transparent;
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  border-radius: 0.125rem;
  cursor: pointer;
  transition: background-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.seat-controls__auto:hover,
.seat-controls__auto:focus-visible {
  background-color: var(--secondary);
  color: var(--surface);
}
</style>
