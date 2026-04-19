<script setup lang="ts">
const RULES = [
  {
    n: '§ 01',
    h: 'Hold, then decide',
    body: 'Seats lock for eight minutes from first tap. Timer pauses while you configure ticket types.',
    emphasis: 'eight minutes',
  },
  {
    n: '§ 02',
    h: 'Late arrival cutoff',
    body: 'Doors close ten minutes after the hour. Late entry dims the programme for everyone — we won\'t reseat you.',
    emphasis: 'ten minutes after the hour',
  },
  {
    n: '§ 03',
    h: 'Move within the tier',
    body: 'Once inside, feel free to move to any unsold seat of the same tier. Ask an usher.',
    emphasis: 'same tier',
  },
  {
    n: '§ 04',
    h: 'Exchanges',
    body: 'Swap seats or showtimes up to 30 minutes before the lights drop. No fees at the box office.',
    emphasis: '30 minutes before',
  },
]
</script>

<template>
  <div class="house-rules">
    <div
      v-for="rule in RULES"
      :key="rule.n"
      class="house-rules__ci"
    >
      <span class="house-rules__n">{{ rule.n }}</span>
      <h4 class="house-rules__h">{{ rule.h }}</h4>
      <p>
        <template v-for="(part, i) in rule.body.split(rule.emphasis)" :key="i">
          {{ part }}<em v-if="i === 0">{{ rule.emphasis }}</em>
        </template>
      </p>
    </div>
  </div>
</template>

<style scoped>
.house-rules {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-lg);
  padding: var(--space-xl) 0 0;
  margin-top: var(--space-xl);
  border-top: 0.0625rem solid rgba(87, 66, 62, 0.2);
}

@media (max-width: 68.75rem) {
  .house-rules { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 40rem) {
  .house-rules { grid-template-columns: 1fr; }
}

.house-rules__ci {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.house-rules__n {
  font-family: var(--font-display);
  font-size: 0.75rem;
  color: var(--secondary);
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
}

.house-rules__h {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: -0.005em;
  color: var(--on-surface);
  margin: 0;
}

.house-rules__ci p {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.5;
  margin: 0;
}

.house-rules__ci p em {
  color: var(--secondary);
  font-style: normal;
}
</style>
