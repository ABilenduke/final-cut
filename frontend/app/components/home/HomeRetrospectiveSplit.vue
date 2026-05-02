<script setup lang="ts">
import { retrospectiveProgramme } from '~/data/homepage'

const p = retrospectiveProgramme
</script>

<template>
  <section class="retro" aria-labelledby="retro-heading">
    <div class="retro__inner">
      <div class="retro__split">
        <div class="retro__media" aria-hidden="true">
          <div class="retro__glyph">{{ p.glyph }}</div>
          <div class="retro__media-label">
            <div class="retro__media-sm">{{ p.tag }}</div>
            <div class="retro__media-big">{{ p.tagMeta }}</div>
          </div>
        </div>

        <div class="retro__body">
          <div class="retro__eyebrow">{{ p.eyebrow }}</div>
          <h2 id="retro-heading" class="retro__title">
            {{ p.title }} <em>{{ p.titleEmphasis }}</em>
          </h2>
          <p class="retro__copy">{{ p.copy }}</p>

          <ol class="retro__schedule">
            <li v-for="s in p.screenings" :key="`${s.day}-${s.title}`" class="retro__row">
              <span class="retro__row-day">{{ s.day }}</span>
              <div class="retro__row-body">
                <b class="retro__row-title">{{ s.title }}</b>
                <span class="retro__row-meta">{{ s.time }} · {{ s.screen }}</span>
              </div>
            </li>
          </ol>

          <CvButton variant="primary" href="/events">
            {{ p.cta }}
            <template #icon-right>
              <span aria-hidden="true">&rarr;</span>
            </template>
          </CvButton>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.retro {
  padding-block: var(--space-4xl);
  padding-inline: var(--space-md);
}

@media (min-width: 40rem) {
  .retro { padding-inline: var(--space-xl); }
}

@media (min-width: 60rem) {
  .retro { padding-inline: var(--space-2xl); }
}

.retro__inner {
  max-width: 90rem;
  margin-inline: auto;
}

.retro__split {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-2xl);
  align-items: center;
}

@media (min-width: 60rem) {
  .retro__split {
    grid-template-columns: 5fr 7fr;
    gap: var(--space-3xl);
  }
}

/* ——— Media panel with large K glyph ——— */
.retro__media {
  position: relative;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background: linear-gradient(180deg, #1a0a0a, var(--surface-container-lowest));
  isolation: isolate;
}

.retro__media::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at 30% 40%, rgb(85 0 0 / 0.6), transparent 60%);
  z-index: 0;
}

.retro__media::after {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(0deg, transparent 0 0.125rem, rgb(255 255 255 / 0.015) 0.125rem 0.1875rem);
  mix-blend-mode: overlay;
  z-index: 1;
}

.retro__glyph {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  z-index: 1;
  font-family: var(--font-display);
  font-size: clamp(8rem, 18vw, 16rem);
  color: var(--primary-container);
  line-height: 1;
  letter-spacing: -0.04em;
  font-style: italic;
  opacity: 0.7;
}

.retro__media-label {
  position: absolute;
  bottom: var(--space-md);
  left: var(--space-md);
  right: var(--space-md);
  z-index: 2;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-md);
}

.retro__media-sm {
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.retro__media-big {
  font-family: var(--font-display);
  font-size: 2rem;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  text-align: right;
  line-height: 1;
}

/* ——— Body ——— */
.retro__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.retro__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.retro__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.5rem);
  line-height: 1;
  letter-spacing: -0.025em;
  color: var(--on-surface);
  margin: 0 0 var(--space-md);
  text-wrap: balance;
}

.retro__title em {
  font-style: italic;
  color: var(--tertiary);
}

.retro__copy {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1.125rem;
  line-height: 1.55;
  max-width: 48ch;
  margin: 0 0 var(--space-xl);
  text-wrap: pretty;
}

/* Schedule list */
.retro__schedule {
  list-style: none;
  padding: 0;
  margin: 0 0 var(--space-xl);
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-md) var(--space-xl);
}

@media (min-width: 40rem) {
  .retro__schedule {
    grid-template-columns: 1fr 1fr;
  }
}

.retro__row {
  display: flex;
  gap: var(--space-md);
  padding-block: 0.75rem;
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.25);
}

.retro__row-day {
  font-family: var(--font-display);
  color: var(--secondary);
  font-size: 0.875rem;
  letter-spacing: 0.1em;
  flex-shrink: 0;
}

.retro__row-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xs);
  min-width: 0;
}

.retro__row-title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--on-surface);
  letter-spacing: -0.01em;
}

.retro__row-meta {
  font-family: var(--font-body);
  color: var(--on-tertiary-fixed-variant);
  font-size: 0.8125rem;
}
</style>
