<script setup lang="ts">
defineProps<{
  post: {
    title: string
    slug: string
    excerpt: string
    date: string
    author: string
    imageUrl: string
  }
}>()
</script>

<template>
  <CvCard
    class="blog-post-card"
    interactive
    :href="`/blog/${post.slug}`"
  >
    <template #header>
      <div class="blog-post-card__image-container">
        <img
          v-if="post.imageUrl"
          :src="post.imageUrl"
          :alt="post.title"
          class="blog-post-card__image"
          loading="lazy"
        />
        <div v-else class="blog-post-card__image-placeholder" />
      </div>
    </template>

    <div class="blog-post-card__content">
      <h3 class="blog-post-card__title headline-sm">{{ post.title }}</h3>
      <p class="blog-post-card__excerpt body-sm">{{ post.excerpt }}</p>
    </div>

    <template #footer>
      <div class="blog-post-card__meta label-md">
        <span class="blog-post-card__date">{{ formatDate(post.date) }}</span>
        <span class="blog-post-card__separator">&middot;</span>
        <span class="blog-post-card__author">{{ post.author }}</span>
      </div>
    </template>
  </CvCard>
</template>

<style scoped>
.blog-post-card__image-container {
  aspect-ratio: 16 / 9;
  background-color: var(--surface-container-lowest);
  border-radius: 0.125rem;
  overflow: hidden;
  margin-bottom: var(--space-sm);
}

.blog-post-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.blog-post-card__image-placeholder {
  width: 100%;
  height: 100%;
  background-color: var(--surface-container-lowest);
}

.blog-post-card__content {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.blog-post-card__title {
  color: var(--on-surface);
  margin: 0;
}

.blog-post-card__excerpt {
  color: var(--tertiary);
  margin: 0;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.blog-post-card__meta {
  color: var(--tertiary);
  display: flex;
  align-items: center;
  gap: var(--space-xs);
}

.blog-post-card__date {
  color: var(--secondary);
}
</style>
