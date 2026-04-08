<script setup lang="ts">
const { data: posts } = await useAsyncData('blog-listing', () =>
  queryCollection('blog')
    .order('date', 'DESC')
    .all()
)

useHead({
  title: 'Blog — Final Cut',
  meta: [
    { name: 'description', content: 'News, behind-the-scenes stories, and updates from Final Cut.' },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Blog',
        name: 'Final Cut Blog',
        description: 'News, behind-the-scenes stories, and updates from Final Cut.',
      }),
    },
  ],
})
</script>

<template>
  <div class="blog-page">
    <div class="container">
      <h1 class="blog-page__title display-sm">Blog</h1>

      <div v-if="posts && posts.length > 0" class="ensemble">
        <BlogPostCard
          v-for="post in posts"
          :key="post.path"
          :post="{
            title: post.title,
            slug: post.stem,
            excerpt: post.description,
            date: post.date,
            author: post.author,
            imageUrl: post.image,
          }"
        />
      </div>

      <p v-else class="blog-page__empty body-md">
        No posts yet. Check back soon.
      </p>
    </div>
  </div>
</template>

<style scoped>
.blog-page {
  padding-block: var(--space-3xl);
}

.blog-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-xl);
}

.blog-page__empty {
  color: var(--tertiary);
  text-align: center;
  padding: var(--space-3xl) 0;
}
</style>
