<script setup lang="ts">
import { faqData } from '~/data/faq'

useHead({
  title: 'FAQ — Final Cut',
  meta: [
    { name: 'description', content: 'Frequently asked questions about tickets, accessibility, food, and policies at Final Cut.' },
    { property: 'og:title', content: 'FAQ — Final Cut' },
    { property: 'og:description', content: 'Frequently asked questions about tickets, accessibility, food, and policies at Final Cut.' },
    { property: 'og:type', content: 'website' },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: safeJsonLd({
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: faqData.flatMap(category =>
          category.items.map(item => ({
            '@type': 'Question',
            name: item.question,
            acceptedAnswer: {
              '@type': 'Answer',
              text: item.answer,
            },
          }))
        ),
      }),
    },
  ],
})
</script>

<template>
  <div class="faq-page">
    <div class="close-up">
      <h1 class="faq-page__title display-sm">Frequently Asked Questions</h1>
      <div class="faq-page__categories">
        <FaqAccordionGroup
          v-for="category in faqData"
          :key="category.category"
          :category="category.category"
          :items="category.items"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.faq-page {
  padding: var(--space-3xl) 0;
}

.faq-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-2xl);
}

.faq-page__categories {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}
</style>
