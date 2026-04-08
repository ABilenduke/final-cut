<script setup lang="ts">
defineProps<{
  category: string
  items: Array<{ question: string; answer: string }>
}>()
</script>

<template>
  <section class="faq-group">
    <h3 class="faq-group__heading headline-sm">{{ category }}</h3>
    <div class="faq-group__items">
      <CvAccordion
        v-for="(item, index) in items"
        :key="index"
        :id="`faq-${category.toLowerCase().replace(/\s+&?\s*/g, '-')}-${index}`"
        :title="item.question"
      >
        <p class="faq-group__answer body-md">{{ item.answer }}</p>
      </CvAccordion>
    </div>
  </section>
</template>

<style scoped>
.faq-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.faq-group__heading {
  color: var(--on-surface);
  margin: 0;
}

.faq-group__items {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.faq-group__answer {
  color: var(--tertiary);
  margin: 0;
  line-height: 1.6;
}
</style>
