<script setup lang="ts">
// Footer block describing how snacks are collected/served. Static editorial copy.
// Each body is modeled as ordered segments so emphasis renders via normal
// template bindings instead of v-html — keeps the pattern consistent with
// MoviePress/SeatSelectionHouseRules and prevents future API-sourced copy
// from smuggling HTML into the page.
interface Segment {
  text: string
  emphasis?: boolean
}
interface Section {
  n: string
  title: string
  body: readonly Segment[]
}

const sections: readonly Section[] = [
  {
    n: '01',
    title: 'Collect at the bar',
    body: [
      { text: 'From ' },
      { text: '45 minutes before', emphasis: true },
      { text: ' showtime. Show your order reference. Wines and draft beers are poured fresh on collection.' },
    ],
  },
  {
    n: '02',
    title: 'Delivered to seat',
    body: [
      { text: 'Opt in at checkout. Runner arrives ' },
      { text: 'before the programme begins', emphasis: true },
      { text: '; no in-film service on 70mm engagements.' },
    ],
  },
  {
    n: '03',
    title: 'Glass, not plastic',
    body: [
      { text: 'Beer is served in stems, wine in proper glassware. Please ' },
      { text: 'return to the bar', emphasis: true },
      { text: ' on your way out.' },
    ],
  },
  {
    n: '04',
    title: 'Auditorium quiet',
    body: [
      { text: 'Late arrivals cut off at showtime — ' },
      { text: 'food runs stop 5 minutes earlier', emphasis: true },
      { text: '. Plan accordingly.' },
    ],
  },
] as const
</script>

<template>
  <section class="collect-info" aria-label="Collection information">
    <article v-for="s in sections" :key="s.n" class="collect-info__item">
      <span class="collect-info__n">§ {{ s.n }}</span>
      <h4 class="collect-info__title">{{ s.title }}</h4>
      <p class="collect-info__body">
        <template v-for="(seg, i) in s.body" :key="i">
          <em v-if="seg.emphasis">{{ seg.text }}</em>
          <template v-else>{{ seg.text }}</template>
        </template>
      </p>
    </article>
  </section>
</template>

<style scoped>
.collect-info {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-lg);
  padding: var(--space-xl) 0 0;
  margin-top: var(--space-xl);
  border-top: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2);
}

.collect-info__item {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.collect-info__n {
  font-family: var(--font-display);
  font-size: 0.75rem;
  color: var(--secondary);
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
}

.collect-info__title {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: -0.005em;
  margin: 0;
  color: var(--on-surface);
}

.collect-info__body {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.5;
  margin: 0;
}

.collect-info__body :deep(em) {
  color: var(--secondary);
  font-style: normal;
}

@media (max-width: 68.75rem) {
  .collect-info {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 40rem) {
  .collect-info {
    grid-template-columns: 1fr;
  }
}
</style>
