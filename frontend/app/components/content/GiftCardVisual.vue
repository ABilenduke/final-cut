<script setup lang="ts">
import { editionMeta } from '~/composables/useGiftCardComposer'
import type { GiftCardEdition } from '~/types/gift-card'
import { formatCurrencyParts } from '~/utils/formatCurrency'

interface Props {
  amountCents: number
  edition: GiftCardEdition
  serial?: string
  volume?: string
}

const props = withDefaults(defineProps<Props>(), {
  serial: 'FC—8AC4 · 9D02 · 7E11',
  volume: 'Vol. XXIII',
})

const amountParts = computed(() => formatCurrencyParts(props.amountCents))
const meta = computed(() => editionMeta(props.edition))
const editionLabel = computed(() => meta.value.label)
const cornerNo = computed(() => meta.value.serial)
</script>

<template>
  <div
    class="gift-card-visual"
    :data-variant="edition"
    role="img"
    :aria-label="`Gift card: ${editionLabel} edition, $${amountParts.whole}.${amountParts.dec}`"
  >
    <span class="gift-card-visual__corner-tl">Final Cut · Gift Card · No. 0{{ cornerNo }}</span>
    <span class="gift-card-visual__corner-tr">{{ volume }}</span>
    <div class="gift-card-visual__amount">
      <span class="gift-card-visual__cur">$</span>{{ amountParts.whole }}<span class="gift-card-visual__dec">.{{ amountParts.dec }}</span>
    </div>
    <div class="gift-card-visual__divider" aria-hidden="true" />
    <div class="gift-card-visual__tagline">Two hours · in the dark</div>
    <div class="gift-card-visual__foot">
      <span class="gift-card-visual__wm">Final Cut<em>est. 2026</em></span>
      <span class="gift-card-visual__serial">{{ serial }}</span>
    </div>
  </div>
</template>

<style scoped>
.gift-card-visual {
  position: relative;
  aspect-ratio: 1.586 / 1;
  border-radius: var(--radius-card);
  overflow: hidden;
  isolation: isolate;
  background: linear-gradient(145deg, #1a0a0a 0%, #0a0605 65%, #000 100%);
  box-shadow: var(--shadow-float);
  transform: perspective(75rem) rotateX(2deg) rotateY(-4deg);
  transition: transform 500ms var(--ease-standard);
}

.gift-card-visual:hover {
  transform: perspective(75rem) rotateX(0deg) rotateY(0deg);
}

.gift-card-visual::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    radial-gradient(circle at 18% 28%, rgba(218, 199, 105, 0.18), transparent 45%),
    radial-gradient(circle at 80% 70%, rgba(85, 0, 0, 0.55), transparent 60%);
}

.gift-card-visual::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    repeating-linear-gradient(135deg, transparent 0 0.25rem, rgba(218, 199, 105, 0.025) 0.25rem 0.3125rem),
    repeating-linear-gradient(0deg, transparent 0 0.125rem, rgba(255, 255, 255, 0.012) 0.125rem 0.1875rem);
  mix-blend-mode: overlay;
}

.gift-card-visual[data-variant="reactor"]::before {
  background:
    radial-gradient(circle at 18% 28%, rgba(218, 199, 105, 0.15), transparent 45%),
    radial-gradient(circle at 80% 70%, rgba(85, 0, 0, 0.65), transparent 60%);
}

.gift-card-visual[data-variant="gold"] {
  background: linear-gradient(145deg, #3a3318 0%, #1a1608 65%, #0a0805 100%);
}

.gift-card-visual[data-variant="gold"]::before {
  background:
    radial-gradient(circle at 25% 30%, rgba(218, 199, 105, 0.40), transparent 55%),
    radial-gradient(circle at 80% 75%, rgba(138, 118, 40, 0.35), transparent 60%);
}

.gift-card-visual[data-variant="void"] {
  background: linear-gradient(145deg, #1c1c1c 0%, #0a0a0a 65%, #000 100%);
}

.gift-card-visual[data-variant="void"]::before {
  background: radial-gradient(circle at 50% 30%, rgba(204, 198, 182, 0.08), transparent 50%);
}

.gift-card-visual__corner-tl {
  position: absolute;
  top: 1.25rem;
  left: 1.5rem;
  z-index: 2;
  font-size: 0.5625rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.gift-card-visual__corner-tr {
  position: absolute;
  top: 1.25rem;
  right: 1.5rem;
  z-index: 2;
  font-family: var(--font-display);
  font-size: 0.75rem;
  color: var(--secondary);
  letter-spacing: 0.18em;
}

.gift-card-visual__amount {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -58%);
  z-index: 2;
  font-family: var(--font-display);
  font-style: italic;
  font-weight: 500;
  font-size: clamp(3rem, 7vw, 5.5rem);
  letter-spacing: -0.03em;
  line-height: 0.9;
  color: var(--on-surface);
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.gift-card-visual__cur {
  font-style: normal;
  font-size: 0.45em;
  color: var(--tertiary);
  vertical-align: 0.65em;
  letter-spacing: 0;
  margin-right: 0.1em;
  font-weight: 400;
}

.gift-card-visual__dec {
  font-style: normal;
  font-size: 0.32em;
  color: var(--tertiary);
  vertical-align: 1.4em;
  letter-spacing: 0;
  margin-left: 0.1em;
  font-weight: 400;
}

.gift-card-visual__divider {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, 3.5em);
  z-index: 2;
  width: 2.5rem;
  height: 1px;
  background: rgba(218, 199, 105, 0.4);
}

.gift-card-visual__tagline {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 3.5rem;
  z-index: 2;
  text-align: center;
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--secondary);
}

.gift-card-visual__foot {
  position: absolute;
  left: 1.5rem;
  right: 1.5rem;
  bottom: 1.25rem;
  z-index: 2;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  font-size: 0.5625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.gift-card-visual__wm {
  font-family: var(--font-display);
  font-size: 1rem;
  color: var(--on-surface);
  letter-spacing: -0.01em;
  text-transform: none;
  display: flex;
  align-items: baseline;
  gap: 0.4em;
}

.gift-card-visual__wm::before {
  content: '◉';
  color: var(--primary-container);
  font-size: 0.85em;
}

.gift-card-visual__wm em {
  font-style: italic;
  color: var(--tertiary);
  font-size: 0.65em;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.gift-card-visual__serial {
  font-family: var(--font-display);
  color: var(--tertiary);
  letter-spacing: 0.16em;
  font-size: 0.625rem;
}

@media (prefers-reduced-motion: reduce) {
  .gift-card-visual {
    transform: none;
    transition: none;
  }
  .gift-card-visual:hover {
    transform: none;
  }
}
</style>
