<script setup lang="ts">
import { blogPosts } from '~/data/blog'

const route = useRoute()
const slug = route.params.slug as string

const post = blogPosts.find(p => p.slug === slug)

if (!post) {
  throw createError({ statusCode: 404, statusMessage: 'Post not found' })
}

const relatedPosts = blogPosts.filter(p => p.slug !== slug).slice(0, 3)

useHead({
  title: `${post.title} — Final Cut Blog`,
  meta: [
    { name: 'description', content: post.excerpt },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: post.title,
        description: post.excerpt,
        datePublished: post.date,
        author: {
          '@type': 'Person',
          name: post.author,
        },
      }),
    },
  ],
})
</script>

<template>
  <div class="blog-post-page">
    <div class="close-up">
      <header class="blog-post-page__header">
        <h1 class="blog-post-page__title display-sm">{{ post.title }}</h1>
        <div class="blog-post-page__meta label-lg">
          <span class="blog-post-page__date">{{ formatDate(post.date) }}</span>
          <span>&middot;</span>
          <span class="blog-post-page__author">{{ post.author }}</span>
        </div>
      </header>

      <div v-if="post.imageUrl" class="blog-post-page__image-container">
        <img
          :src="post.imageUrl"
          :alt="post.title"
          class="blog-post-page__image"
        />
      </div>

      <div class="blog-post-page__body body-md">
        <p v-for="(paragraph, i) in post.body.split('\n\n')" :key="i">
          {{ paragraph }}
        </p>
      </div>

      <section v-if="relatedPosts.length > 0" class="blog-post-page__related">
        <h2 class="blog-post-page__related-heading headline-md">More from the Blog</h2>
        <div class="ensemble">
          <BlogPostCard
            v-for="related in relatedPosts"
            :key="related.slug"
            :post="related"
          />
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.blog-post-page {
  padding-block: var(--space-3xl);
}

.blog-post-page__header {
  margin-bottom: var(--space-xl);
}

.blog-post-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-sm);
}

.blog-post-page__meta {
  color: var(--tertiary);
  display: flex;
  align-items: center;
  gap: var(--space-xs);
}

.blog-post-page__date {
  color: var(--secondary);
}

.blog-post-page__image-container {
  aspect-ratio: 16 / 9;
  background-color: var(--surface-container-lowest);
  border-radius: 0.125rem;
  overflow: hidden;
  margin-bottom: var(--space-xl);
}

.blog-post-page__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.blog-post-page__body {
  color: var(--on-surface);
  line-height: 1.7;
}

.blog-post-page__body p {
  margin: 0 0 var(--space-md);
}

.blog-post-page__related {
  margin-top: var(--space-3xl);
  padding-top: var(--space-2xl);
}

.blog-post-page__related-heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-xl);
}
</style>
