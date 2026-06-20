<script setup lang="ts">
type PickSeat = {
  id: string
  label: string
  row: string
  number: number
}

const props = defineProps<{
  pickSeats: PickSeat[]
}>()

const emit = defineEmits<{
  'take-pick': []
}>()

const label = computed(() => {
  if (props.pickSeats.length === 0) return ''
  if (props.pickSeats.length === 1) return `Seat ${props.pickSeats[0].label}`
  const row = props.pickSeats[0].row
  const nums = props.pickSeats.map(s => s.number).join(' & ')
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
    <CvButton variant="secondary" size="sm" class="pick__btn" @click="emit('take-pick')">
      Take these seats
    </CvButton>
    <div class="pick__by">
      Chosen by <b>M. Varga</b> · Chief projectionist
    </div>
  </section>
</template>

<style scoped>
.pick {
  padding: var(--space-lg);
  background-color: var(--surface-container-low);
  border: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.2);
  border-radius: var(--radius-card);
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
  height: var(--border-hairline);
  background-color: rgb(var(--secondary-rgb) / 0.2);
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
  position: relative;
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
