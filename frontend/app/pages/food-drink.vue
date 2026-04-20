<script setup lang="ts">
const { items, fetchMenu } = useFoodMenu()

await fetchMenu()

useHead({
  title: 'Food & Drink — Final Cut',
  meta: [
    { name: 'description', content: 'Curated bar and concessions menu — small selection, better quality. Pre-order with your tickets.' },
    { property: 'og:title', content: 'Food & Drink — Final Cut' },
    { property: 'og:description', content: 'Curated bar and concessions menu — small selection, better quality. Pre-order with your tickets.' },
    { property: 'og:type', content: 'website' },
  ],
})
</script>

<template>
  <div class="food-drink-page">
    <div class="container food-drink-page__container">
      <header class="food-drink-page__top">
        <div>
          <p class="food-drink-page__eyebrow">Reel · Concessions</p>
          <h1 class="food-drink-page__title">
            <em>The bar &amp;</em> snack counter.
          </h1>
          <p class="food-drink-page__lede">
            Curated by the programming team — small selection, better quality.
            Browse the counter; pre-order with your tickets when you book.
          </p>
        </div>
      </header>

      <ConcessionsCatalog
        :items="items"
        :interactive="false"
      />

      <ConcessionsAllergenNotice />

      <ConcessionsCollectionInfo />
    </div>
  </div>
</template>

<style scoped>
.food-drink-page {
  padding: var(--space-3xl) 0 var(--space-4xl);
}

.food-drink-page__container {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.food-drink-page__top {
  padding: var(--space-xl) 0 var(--space-lg);
  border-bottom: 0.0625rem solid rgb(var(--outline-variant-rgb) / 0.2);
}

.food-drink-page__eyebrow {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0 0 0.5rem;
}

.food-drink-page__eyebrow::before {
  content: '—';
  color: var(--secondary);
}

.food-drink-page__title {
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

.food-drink-page__title em {
  font-style: italic;
  color: var(--tertiary);
}

.food-drink-page__lede {
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 1rem;
  max-width: 52ch;
  margin: var(--space-md) 0 0;
  line-height: 1.55;
  font-style: italic;
}
</style>
