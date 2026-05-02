<script setup lang="ts">
import type { Showtime } from '~/types/showtime'
import type { Auditorium } from '~/types/auditorium'

const props = defineProps<{
  showtime: Showtime
  auditorium: Auditorium
}>()

const formattedWhen = computed<string>(() => formatDateTime(props.showtime.startTime))
</script>

<template>
  <header class="seat-hero">
    <div class="seat-hero__lead">
      <div class="seat-hero__eyebrow">Reel 01 · Seat selection</div>
      <h1 class="seat-hero__title">
        <em>Pick your</em> vantage point.
      </h1>
      <p class="seat-hero__lede">
        {{ auditorium.name }} — our flagship house. The raked floor favours the middle rows;
        the back rows are for those who like their picture framed whole. Selections hold for
        eight minutes.
      </p>
    </div>

    <div class="seat-hero__meta">
      <b>{{ formattedWhen }}</b>
      {{ showtime.movieTitle }} · {{ auditorium.name }}
    </div>
  </header>
</template>

<style scoped>
.seat-hero {
  padding: var(--space-xl) 0 var(--space-lg);
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-lg);
  flex-wrap: wrap;
  border-bottom: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.seat-hero__lead {
  max-width: 36rem;
  min-width: 0;
}

.seat-hero__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.seat-hero__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.seat-hero__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.25rem);
  line-height: 1;
  letter-spacing: -0.03em;
  text-wrap: balance;
  max-width: 18ch;
  color: var(--on-surface);
  margin: 0;
}

.seat-hero__title em {
  font-style: italic;
  color: var(--tertiary);
}

.seat-hero__lede {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1rem;
  max-width: 56ch;
  margin-top: var(--space-md);
  line-height: 1.55;
  font-style: italic;
}

.seat-hero__meta {
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
}

.seat-hero__meta b {
  font-family: var(--font-display);
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: 0.04em;
  font-size: 0.9375rem;
  text-transform: none;
  display: block;
  margin-bottom: 0.2rem;
}
</style>
