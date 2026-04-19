<script setup lang="ts">
import type { Auditorium, Seat } from '~/types/auditorium'

const props = defineProps<{
  auditorium: Auditorium
  seats: Seat[]
  selectedSeatIds: string[]
}>()

const emit = defineEmits<{
  'seat-toggled': [payload: { seatId: string; selected: boolean }]
}>()

const rowCount = computed(() => new Set(props.seats.map(s => s.row)).size)
</script>

<template>
  <section class="stage">
    <header class="stage__head">
      <div>
        <span class="stage__num">§ 01 · Auditorium</span>
        <h2 class="stage__title"><em>{{ auditorium.name }}</em> — the flagship house.</h2>
      </div>
      <div class="stage__info" aria-hidden="true">
        <b>{{ auditorium.totalSeats }}</b> seats total<br>
        {{ rowCount }} rows · raked floor · 70mm projection
      </div>
    </header>

    <div class="stage__screen-wrap">
      <div class="stage__screen-label">Screen · 24m, curved</div>
      <div class="stage__screen" aria-hidden="true" />
      <div class="stage__screen-bloom" aria-hidden="true" />
    </div>

    <div class="stage__map">
      <AuditoriumGrid
        :auditorium="auditorium"
        :seats="seats"
        :selected-seat-ids="selectedSeatIds"
        @seat-toggled="(p) => emit('seat-toggled', p)"
      />
    </div>

    <footer class="stage__foot">
      <span class="stage__ent"><span class="stage__arr" aria-hidden="true">↳</span> Entrance — house left</span>
      <span class="stage__aisle-note">§ Aisle · · Aisle §</span>
      <span class="stage__ent">House right — exit <span class="stage__arr" aria-hidden="true">↰</span></span>
    </footer>
  </section>
</template>

<style scoped>
.stage {
  position: relative;
  background-color: var(--surface-container-low);
  border: 0.0625rem solid rgba(87, 66, 62, 0.15);
  border-radius: 0.25rem;
  padding: var(--space-xl) var(--space-lg) var(--space-lg);
  overflow: hidden;
}

.stage::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    radial-gradient(ellipse 80% 50% at 50% 0%, rgba(218, 199, 105, 0.08), transparent 70%),
    radial-gradient(ellipse 70% 60% at 50% 100%, rgba(85, 0, 0, 0.12), transparent 70%);
}

.stage__head {
  position: relative;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-md);
  padding-bottom: var(--space-md);
  margin-bottom: var(--space-sm);
  border-bottom: 0.0625rem solid rgba(87, 66, 62, 0.2);
  flex-wrap: wrap;
}

.stage__num {
  display: block;
  font-family: var(--font-display);
  font-size: 0.8125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
  margin-bottom: 0.35rem;
}

.stage__title {
  font-family: var(--font-display);
  font-size: 1.625rem;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.1;
  margin: 0;
  color: var(--on-surface);
}

.stage__title em {
  font-style: italic;
  color: var(--tertiary);
}

.stage__info {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.2em;
  text-transform: uppercase;
  text-align: right;
  line-height: 1.5;
}

.stage__info b {
  color: var(--tertiary);
  font-family: var(--font-display);
  letter-spacing: 0.04em;
  font-weight: 500;
}

/* Screen bar */
.stage__screen-wrap {
  position: relative;
  margin: var(--space-lg) auto 0;
  max-width: 45rem;
}

.stage__screen-label {
  position: absolute;
  top: -0.75rem;
  left: 50%;
  transform: translateX(-50%);
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.4em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  background-color: var(--surface-container-low);
  padding: 0 0.8rem;
  z-index: 1;
}

.stage__screen {
  height: 2.5rem;
  margin: 0 auto;
  position: relative;
  background: linear-gradient(
    180deg,
    rgba(218, 199, 105, 0.9) 0%,
    rgba(218, 199, 105, 0.5) 60%,
    transparent 100%
  );
  clip-path: polygon(6% 0%, 94% 0%, 100% 100%, 0% 100%);
  box-shadow: 0 0 3rem rgba(218, 199, 105, 0.18);
}

.stage__screen::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    90deg,
    transparent 0 0.1875rem,
    rgba(0, 0, 0, 0.08) 0.1875rem 0.25rem
  );
}

.stage__screen-bloom {
  position: relative;
  height: 6rem;
  margin-top: -0.0625rem;
  background: radial-gradient(
    ellipse 60% 100% at 50% 0%,
    rgba(218, 199, 105, 0.22),
    transparent 70%
  );
  pointer-events: none;
}

.stage__screen-bloom::after {
  content: '— house lights at 25% — the picture begins —';
  position: absolute;
  left: 50%;
  bottom: 1rem;
  transform: translateX(-50%);
  font-family: var(--font-body);
  font-style: italic;
  color: var(--on-tertiary-fixed-variant);
  font-size: 0.75rem;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

@media (max-width: 40rem) {
  .stage__screen-bloom::after {
    font-size: 0.625rem;
    letter-spacing: 0.02em;
    bottom: 0.5rem;
  }
}

.stage__map {
  position: relative;
  padding: var(--space-md) 0 var(--space-lg);
}

/* Floor plan footer */
.stage__foot {
  position: relative;
  margin-top: var(--space-md);
  padding-top: var(--space-md);
  border-top: 0.0625rem dashed rgba(87, 66, 62, 0.3);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-md);
  font-family: var(--font-body);
  font-size: 0.5625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  flex-wrap: wrap;
}

.stage__ent {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.stage__arr {
  color: var(--secondary);
  font-family: var(--font-display);
}

.stage__aisle-note {
  white-space: nowrap;
}
</style>
