<script setup lang="ts">
useHead({
  title: 'Gift Cards — Final Cut',
  meta: [
    { name: 'description', content: 'Purchase a Final Cut gift card for the movie lover in your life, or check your existing balance.' },
    { property: 'og:title', content: 'Gift Cards — Final Cut' },
    { property: 'og:description', content: 'Purchase a Final Cut gift card for the movie lover in your life, or check your existing balance.' },
    { property: 'og:type', content: 'website' },
  ],
})
</script>

<template>
  <div class="gift-cards-page">
    <div class="container">
      <h1 class="gift-cards-page__title display-sm">Gift Cards</h1>
      <div class="establishing-shot">
        <div class="establishing-shot__primary">
          <GiftCardPurchase />
        </div>
        <div class="establishing-shot__secondary">
          <BalanceChecker />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.gift-cards-page {
  padding: var(--space-3xl) 0;
}

.gift-cards-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-2xl);
}
</style>
