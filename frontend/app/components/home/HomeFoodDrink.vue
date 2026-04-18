<script setup lang="ts">
import { foodItems } from '~/data/homepage'
</script>

<template>
  <section class="food" aria-labelledby="food-heading">
    <div class="food__inner">
      <header class="food__head">
        <div>
          <div class="food__eyebrow">Lobby Bar · Open 60 min before curtain</div>
          <h2 id="food-heading" class="food__title">
            Provisions <em>for the programme.</em>
          </h2>
        </div>
        <NuxtLink to="/food-drink" class="food__link">Full Menu &rarr;</NuxtLink>
      </header>

      <div class="food__grid">
        <article
          v-for="item in foodItems"
          :key="item.index"
          class="food__card"
        >
          <span class="food__num">{{ item.index }}</span>
          <h3 class="food__name">{{ item.name }}</h3>
          <p class="food__desc">{{ item.description }}</p>
          <footer class="food__foot">
            <span class="food__meta">{{ item.meta }}</span>
            <span class="food__price">{{ item.priceLabel }}</span>
          </footer>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.food {
  padding-block: var(--space-4xl);
  padding-inline: var(--space-md);
}

@media (min-width: 40rem) {
  .food { padding-inline: var(--space-xl); }
}

@media (min-width: 60rem) {
  .food { padding-inline: var(--space-2xl); }
}

.food__inner {
  max-width: 90rem;
  margin-inline: auto;
}

.food__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-xl);
  margin-bottom: var(--space-2xl);
}

.food__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.food__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.food__title {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2rem, 4vw, 3.5rem);
  line-height: 1;
  letter-spacing: -0.025em;
  color: var(--on-surface);
  margin: 0.75rem 0 0;
  text-wrap: balance;
}

.food__title em {
  font-style: italic;
  color: var(--tertiary);
}

.food__link {
  display: inline-flex;
  align-items: center;
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: 0.875rem;
  text-decoration: none;
  padding-block: var(--space-xs);
  border-bottom: 0.0625rem solid transparent;
  transition: border-color var(--duration-standard) var(--ease-standard);
}

.food__link:hover { border-bottom-color: var(--secondary); }

/* ——— Grid ——— */
.food__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-lg);
}

@media (min-width: 40rem) {
  .food__grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (min-width: 60rem) {
  .food__grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

/* ——— Card ——— */
.food__card {
  position: relative;
  padding: var(--space-xl);
  background-color: var(--surface-container);
  border-radius: 0.125rem; /* token-exception: card radius */
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  min-height: 17.5rem;
  overflow: hidden;
  isolation: isolate;
  transition:
    transform var(--duration-standard) var(--ease-standard),
    background-color var(--duration-standard) var(--ease-standard);
}

.food__card::before {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  width: 60%;
  height: 60%;
  background: radial-gradient(ellipse at top right, rgb(218 199 105 / 0.08), transparent 60%);
  pointer-events: none;
  z-index: -1;
}

.food__card:hover {
  transform: translateY(-0.1875rem);
  background-color: var(--surface-container-high);
}

.food__num {
  font-family: var(--font-display);
  font-size: 0.75rem;
  letter-spacing: 0.3em;
  color: var(--on-tertiary-fixed-variant);
}

.food__name {
  font-family: var(--font-display);
  font-size: 1.625rem;
  letter-spacing: -0.015em;
  line-height: 1.05;
  color: var(--on-surface);
  font-weight: 500;
  margin: 0;
}

.food__desc {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 0.9375rem;
  line-height: 1.5;
  flex: 1;
  text-wrap: pretty;
  margin: 0;
}

.food__foot {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--space-md);
  padding-top: var(--space-md);
  border-top: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2); /* token-exception: sub-pixel edge */
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.food__meta {
  flex: 1;
  min-width: 0;
}

.food__price {
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--secondary);
  letter-spacing: -0.01em;
  text-transform: none;
}
</style>
