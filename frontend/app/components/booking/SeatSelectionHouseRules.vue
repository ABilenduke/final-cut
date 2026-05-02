<script setup lang="ts">
interface Rule {
  n: string
  h: string
  body: string
  emphasis: string
}

const RULES: readonly Rule[] = [
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

/**
 * Split a rule body around its emphasis marker. Returns an ordered list of
 * { text, emphasis } segments so each rule renders as mixed plain + <em>
 * fragments. If the emphasis string isn't present in the body, the rule
 * gracefully renders as a single plain segment.
 */
function segmentsFor(rule: Rule): Array<{ text: string; emphasis: boolean }> {
  const parts = rule.body.split(rule.emphasis)
  if (parts.length === 1) {
    return [{ text: rule.body, emphasis: false }]
  }
  const segments: Array<{ text: string; emphasis: boolean }> = []
  parts.forEach((part, i) => {
    if (part) segments.push({ text: part, emphasis: false })
    if (i < parts.length - 1) segments.push({ text: rule.emphasis, emphasis: true })
  })
  return segments
}
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
        <template v-for="(seg, i) in segmentsFor(rule)" :key="i">
          <em v-if="seg.emphasis">{{ seg.text }}</em>
          <template v-else>{{ seg.text }}</template>
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
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
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
