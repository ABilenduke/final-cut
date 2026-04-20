<script setup lang="ts">
const props = defineProps<{
  pickSeats: string[]
}>()

const emit = defineEmits<{
  'take-pick': []
}>()

const label = computed(() => {
  if (props.pickSeats.length < 2) return props.pickSeats[0] ?? ''
  const row = props.pickSeats[0].match(/^([A-Z]+)/)?.[1] ?? ''
  const nums = props.pickSeats.map(s => s.replace(/^[A-Z]+/, '')).join(' & ')
  return `Row ${row} · seats ${nums}`
})
</script>

<template>
  <section v-if="pickSeats.length > 0" class="pick">
    <div class="pick__tag" aria-hidden="true">Projectionist's pick</div>
    <h3 class="pick__title">
      <em>{{ label }}</em>
    </h3>
    <p>
      <span class="pick__mark" aria-hidden="true">¶</span>
      Dead centre of the sweet spot. The picture fills the eye without spilling it. Best
      pair of seats in the house for tonight's run — and they're still open.
    </p>
    <button type="button" class="pick__btn" @click="emit('take-pick')">
      Take these seats
    </button>
    <div class="pick__by">
      Chosen by <b>M. Varga</b> · Chief projectionist
    </div>
  </section>
</template>

<style scoped>
.pick {
  padding: var(--space-lg);
  background-color: var(--surface-container-low);
  border: 0.0625rem solid rgba(218, 199, 105, 0.2);
  border-radius: 0.25rem;
  position: relative;
  overflow: hidden;
}

.pick::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: radial-gradient(
    ellipse 80% 60% at 100% 0%,
    rgba(196, 112, 64, 0.1),
    transparent 60%
  );
}

.pick__tag {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--secondary);
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 0.5rem;
  position: relative;
}

.pick__tag::after {
  content: '';
  flex: 1;
  height: 0.0625rem;
  background-color: rgba(218, 199, 105, 0.2);
}

.pick__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.2;
  color: var(--on-surface);
  margin: 0;
  position: relative;
}

.pick__title em {
  font-style: italic;
  color: var(--tertiary);
}

.pick p {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.55;
  margin: 0.5rem 0 0;
  font-style: italic;
  position: relative;
}

.pick__mark {
  color: var(--secondary);
  font-style: normal;
}

.pick__btn {
  margin-top: var(--space-md);
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
  position: relative;
}

.pick__btn:hover,
.pick__btn:focus-visible {
  background-color: var(--secondary);
  color: var(--surface);
}

.pick__by {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: var(--space-md);
  position: relative;
}

.pick__by b {
  color: var(--on-surface);
  font-weight: 500;
}
</style>
