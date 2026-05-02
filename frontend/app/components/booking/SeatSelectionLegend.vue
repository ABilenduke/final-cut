<script setup lang="ts">
const props = defineProps<{
  standardFrom?: number
  premiereFrom?: number
  accessibleFrom?: number
}>()

function fmt(cents: number | undefined, fallback: string): string {
  return typeof cents === 'number' ? formatCurrency(cents) : fallback
}
</script>

<template>
  <div class="seat-legend">
    <div class="seat-legend__item">
      <div class="seat-legend__sw seat-legend__sw--standard" aria-hidden="true">STD</div>
      <div>
        <div class="seat-legend__t">Standard <em>orchestra</em></div>
        <div class="seat-legend__d">Middle rows · upholstered fixed.</div>
        <span class="seat-legend__pr">from {{ fmt(props.standardFrom, '$18.50') }}</span>
      </div>
    </div>

    <div class="seat-legend__item">
      <div class="seat-legend__sw seat-legend__sw--premiere" aria-hidden="true">PRM</div>
      <div>
        <div class="seat-legend__t">Premiere <em>recliner</em></div>
        <div class="seat-legend__d">Front rows · full recline, side tray, heated.</div>
        <span class="seat-legend__pr">{{ fmt(props.premiereFrom, '$28.50') }}</span>
      </div>
    </div>

    <div class="seat-legend__item">
      <div class="seat-legend__sw seat-legend__sw--companion" aria-hidden="true">◍◍</div>
      <div>
        <div class="seat-legend__t">Companion <em>pair</em></div>
        <div class="seat-legend__d">Ask at the box office — shared armrest, bring a friend.</div>
        <span class="seat-legend__pr">$52 / pair</span>
      </div>
    </div>

    <div class="seat-legend__item">
      <div class="seat-legend__sw seat-legend__sw--access" aria-hidden="true">♿</div>
      <div>
        <div class="seat-legend__t">Accessible <em>&amp; companion</em></div>
        <div class="seat-legend__d">Back row · wheelchair position + adjoining seat.</div>
        <span class="seat-legend__pr">{{ fmt(props.accessibleFrom, '$18.50') }}</span>
      </div>
    </div>

    <div class="seat-legend__states">
      <div class="seat-legend__state">
        <div class="seat-legend__sw seat-legend__sw--selected" aria-hidden="true">✓</div>
        <span>Selected</span>
      </div>
      <div class="seat-legend__state">
        <div class="seat-legend__sw seat-legend__sw--sold" aria-hidden="true" />
        <span>Sold</span>
      </div>
      <div class="seat-legend__state">
        <div class="seat-legend__sw seat-legend__sw--held" aria-hidden="true">—</div>
        <span>Held</span>
      </div>
      <div class="seat-legend__note">Prices include tax · no service fees</div>
    </div>
  </div>
</template>

<style scoped>
.seat-legend {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-md);
  padding: var(--space-lg);
  background-color: var(--surface-container-low);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.15);
  border-radius: var(--radius-card);
}

@media (max-width: 68.75rem) {
  .seat-legend { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 40rem) {
  .seat-legend { grid-template-columns: 1fr; }
}

.seat-legend__item {
  display: flex;
  gap: var(--space-md);
  align-items: flex-start;
}

.seat-legend__sw {
  width: 2rem;
  height: 2rem;
  border-radius: 0.3rem 0.3rem 0.2rem 0.2rem;
  flex-shrink: 0;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-size: 0.625rem;
  color: var(--on-tertiary-fixed-variant);
  background-color: var(--surface-container);
}

.seat-legend__sw--premiere {
  background-color: rgba(85, 0, 0, 0.18);
  border-color: rgba(168, 110, 100, 0.35);
  color: var(--tertiary);
}

.seat-legend__sw--companion {
  background-color: rgba(85, 0, 0, 0.22);
  border-color: rgba(168, 110, 100, 0.5);
  color: var(--tertiary);
}

.seat-legend__sw--access {
  background-color: rgba(42, 42, 42, 0.6);
  border-color: rgba(168, 139, 134, 0.4);
  color: var(--tertiary);
}

.seat-legend__sw--selected {
  background-color: var(--secondary);
  color: var(--surface);
  border-color: var(--secondary);
}

.seat-legend__sw--sold {
  background-color: var(--surface-container-lowest);
  border-color: rgb(var(--outline-variant-rgb) / 0.3);
  position: relative;
  color: transparent;
}

.seat-legend__sw--sold::after {
  content: '';
  position: absolute;
  inset: 25%;
  background: linear-gradient(
    45deg,
    transparent 45%,
    rgba(138, 128, 116, 0.35) 45% 55%,
    transparent 55%
  );
}

.seat-legend__sw--held {
  background-color: rgba(255, 180, 120, 0.08);
  border-style: dashed;
  border-color: rgba(196, 112, 64, 0.35);
  color: transparent;
}

.seat-legend__t {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  color: var(--on-surface);
  letter-spacing: -0.005em;
  line-height: 1.15;
}

.seat-legend__t em {
  font-style: italic;
  color: var(--tertiary);
  font-weight: 400;
}

.seat-legend__d {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--tertiary);
  line-height: 1.45;
  margin-top: 0.15rem;
}

.seat-legend__pr {
  display: block;
  margin-top: 0.25rem;
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
  color: var(--secondary);
  font-size: 0.8125rem;
  letter-spacing: 0.04em;
}

.seat-legend__states {
  grid-column: 1 / -1;
  border-top: var(--border-hairline) dashed rgb(var(--outline-variant-rgb) / 0.3);
  padding-top: var(--space-md);
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-lg);
  align-items: center;
}

.seat-legend__state {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
}

.seat-legend__state .seat-legend__sw {
  width: 1.5rem;
  height: 1.5rem;
}

.seat-legend__note {
  margin-left: auto;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}
</style>
