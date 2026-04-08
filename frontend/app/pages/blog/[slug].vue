<script setup lang="ts">
const route = useRoute()

const { data: post } = await useAsyncData(`blog-${route.path}`, () =>
  queryCollection('blog').path(route.path).first()
)

if (!post.value) {
  throw createError({ statusCode: 404, statusMessage: 'Post not found' })
}

// Related posts: 3 most recent excluding current
const { data: relatedPosts } = await useAsyncData(`blog-related-${route.path}`, () =>
  queryCollection('blog')
    .where('path', '<>', route.path)
    .order('date', 'DESC')
    .limit(3)
    .all()
)

// SEO
useHead({
  title: computed(() =>
    post.value
      ? `${post.value.title} — Final Cut Blog`
      : 'Blog — Final Cut',
  ),
  meta: [
    {
      name: 'description',
      content: computed(() => post.value?.description ?? 'Read the latest from Final Cut.'),
    },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: computed(() =>
        JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'Article',
          headline: post.value?.title ?? '',
          description: post.value?.description ?? '',
          datePublished: post.value?.date ?? '',
          author: {
            '@type': 'Person',
            name: post.value?.author ?? '',
          },
        })
      ),
    },
  ],
})
</script>

<template>
  <div class="blog-post-page">
    <div class="close-up" v-if="post">
      <header class="blog-post-page__header">
        <h1 class="blog-post-page__title display-sm">{{ post.title }}</h1>
        <div class="blog-post-page__meta label-lg">
          <span class="blog-post-page__date">{{ formatDate(post.date) }}</span>
          <span>&middot;</span>
          <span class="blog-post-page__author">{{ post.author }}</span>
        </div>
      </header>

      <div v-if="post.image" class="blog-post-page__image-container">
        <img
          :src="post.image"
          :alt="post.title"
          class="blog-post-page__image"
        />
      </div>

      <div class="blog-post-page__body">
        <ContentRenderer :value="post" />
      </div>

      <!-- Related posts -->
      <section v-if="relatedPosts && relatedPosts.length > 0" class="blog-post-page__related">
        <h2 class="blog-post-page__related-heading headline-md">More from the Blog</h2>
        <div class="ensemble">
          <BlogPostCard
            v-for="related in relatedPosts"
            :key="related.path"
            :post="{
              title: related.title,
              slug: related.stem,
              excerpt: related.description,
              date: related.date,
              author: related.author,
              imageUrl: related.image,
            }"
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

.blog-post-page__body :deep(h1),
.blog-post-page__body :deep(h2),
.blog-post-page__body :deep(h3) {
  color: var(--on-surface);
  margin-top: var(--space-xl);
  margin-bottom: var(--space-md);
}

.blog-post-page__body :deep(h1) {
  display: none;
}

.blog-post-page__body :deep(h2) {
  font-family: var(--font-noto-serif);
  font-size: clamp(1.25rem, 0.85rem + 1vw, 1.75rem);
  letter-spacing: -0.02em;
}

.blog-post-page__body :deep(h3) {
  font-family: var(--font-noto-serif);
  font-size: clamp(1.125rem, 0.825rem + 0.75vw, 1.5rem);
  letter-spacing: -0.02em;
}

.blog-post-page__body :deep(p) {
  margin: 0 0 var(--space-md);
  font-family: var(--font-newsreader);
}

.blog-post-page__body :deep(a) {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}

.blog-post-page__body :deep(ul),
.blog-post-page__body :deep(ol) {
  margin: 0 0 var(--space-md);
  padding-left: var(--space-lg);
}

.blog-post-page__body :deep(li) {
  margin-bottom: var(--space-xs);
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
