<script setup lang="ts">
</script>

<template>
  <section class="sightline" aria-labelledby="sightline-title">
    <div class="sightline__text">
      <span class="sightline__n">§ 02 · Sightline</span>
      <h3 id="sightline-title" class="sightline__title">
        The <em>sweet spot</em> is the <em>middle row.</em>
      </h3>
      <p>
        Our projectionist measures the ideal viewing distance as 1.4 × the screen width.
        Close enough for the grain, far enough for the frame. The front rows are recliners,
        tilted to match.
      </p>
      <ul class="sightline__list">
        <li><b>Front</b> · 6.5 m</li>
        <li><b>Middle</b> · 13.0 m · <span class="sightline__ideal">ideal</span></li>
        <li><b>Back</b> · 22.0 m</li>
        <li><b>Angle</b> · 34°</li>
      </ul>
    </div>

    <div class="sightline__diagram" aria-hidden="true">
      <svg viewBox="0 0 260 260" preserveAspectRatio="xMidYMid meet">
        <rect x="30" y="30" width="200" height="6" fill="rgba(218,199,105,0.85)" />
        <rect x="30" y="36" width="200" height="3" fill="rgba(218,199,105,0.35)" />
        <text
          x="130" y="24"
          text-anchor="middle"
          font-family="Noto Serif"
          font-size="8"
          fill="rgba(204,198,182,0.5)"
          letter-spacing="3"
          style="text-transform:uppercase;"
        >SCREEN</text>

        <path
          d="M 30 36 L 130 230 L 230 36 Z"
          fill="rgba(218,199,105,0.05)"
          stroke="rgba(218,199,105,0.2)"
          stroke-dasharray="2 3"
        />

        <g stroke="rgba(168,139,134,0.3)" fill="none">
          <path d="M 60 230 Q 130 215 200 230" stroke-width="1" />
          <path d="M 55 210 Q 130 192 205 210" stroke-width="1" />
          <path d="M 50 188 Q 130 168 210 188" stroke-width="1" />
          <path d="M 45 164 Q 130 142 215 164" stroke-width="1" />
          <path
            d="M 42 138 Q 130 114 218 138"
            stroke-width="1.5"
            stroke="rgba(218,199,105,0.7)"
          />
          <path d="M 38 110 Q 130 84 222 110" stroke-width="1" />
          <path d="M 34 80 Q 130 52 226 80" stroke-width="1" />
        </g>

        <circle cx="130" cy="128" r="6" fill="#dac769" opacity="0.3" />
        <circle cx="130" cy="128" r="3" fill="#dac769" />
        <text
          x="130" y="152"
          text-anchor="middle"
          font-family="Noto Serif"
          font-size="9"
          font-style="italic"
          fill="#dac769"
        >Sweet spot</text>

        <text
          x="20" y="240"
          font-family="Noto Serif"
          font-size="7"
          fill="rgba(168,139,134,0.5)"
          letter-spacing="1.5"
          style="text-transform:uppercase;"
        >L</text>
        <text
          x="22" y="84"
          font-family="Noto Serif"
          font-size="7"
          fill="rgba(168,139,134,0.5)"
          letter-spacing="1.5"
          style="text-transform:uppercase;"
        >A</text>

        <line
          x1="244" y1="36" x2="244" y2="230"
          stroke="rgba(168,139,134,0.3)"
          stroke-width="0.5"
          stroke-dasharray="1 2"
        />
        <text
          x="252" y="135"
          font-family="Noto Serif"
          font-size="7"
          fill="rgba(204,198,182,0.55)"
          letter-spacing="1"
          transform="rotate(-90 252 135)"
        >22 M</text>
      </svg>
    </div>
  </section>
</template>

<style scoped>
.sightline {
  display: grid;
  grid-template-columns: 1fr 16.25rem;
  gap: var(--space-xl);
  padding: var(--space-lg) var(--space-xl);
  background-color: var(--surface-container);
  border: 0.0625rem solid rgba(87, 66, 62, 0.2);
  border-radius: 0.25rem;
  align-items: center;
}

@media (max-width: 68.75rem) {
  .sightline { grid-template-columns: 1fr; }
  .sightline__diagram { max-width: 16.25rem; margin: 0 auto; }
}

.sightline__n {
  font-family: var(--font-display);
  font-size: 0.75rem;
  color: var(--secondary);
  letter-spacing: 0.08em;
  font-variant-numeric: tabular-nums;
  display: block;
  margin-bottom: 0.25rem;
}

.sightline__title {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.2;
  color: var(--on-surface);
  margin: 0;
}

.sightline__title em {
  font-style: italic;
  color: var(--tertiary);
}

.sightline p {
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--tertiary);
  line-height: 1.55;
  margin-top: 0.5rem;
  font-style: italic;
}

.sightline__list {
  list-style: none;
  padding: 0;
  margin: var(--space-sm) 0 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1rem;
}

.sightline__list li {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.sightline__list li b {
  color: var(--secondary);
  font-family: var(--font-display);
  letter-spacing: 0.04em;
  font-weight: 500;
}

.sightline__ideal {
  color: var(--secondary);
}

.sightline__diagram {
  position: relative;
  aspect-ratio: 1 / 1;
  background: radial-gradient(
    ellipse at 50% 100%,
    rgba(14, 14, 14, 0.8),
    rgba(14, 14, 14, 0.3)
  );
  border-radius: 0.125rem;
  overflow: hidden;
}

.sightline__diagram svg {
  width: 100%;
  height: 100%;
}
</style>
