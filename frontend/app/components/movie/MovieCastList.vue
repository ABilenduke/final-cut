<script setup lang="ts">
import type { CastMember } from '~/types/movie'

defineProps<{
  cast: CastMember[]
}>()
</script>

<template>
  <div v-if="cast.length > 0" class="cast-list">
    <h3 class="cast-list__heading label-lg">Cast</h3>
    <div class="cast-list__scroll">
      <div v-for="member in cast" :key="member.id" class="cast-list__member">
        <div class="cast-list__avatar">
          <img
            v-if="member.profileUrl"
            :src="member.profileUrl"
            :alt="member.name"
            class="cast-list__photo"
            loading="lazy"
          />
        </div>
        <span class="cast-list__name label-lg">{{ member.name }}</span>
        <span class="cast-list__character label-md">{{ member.character }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cast-list__heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-sm);
}

.cast-list__scroll {
  display: flex;
  gap: var(--space-md);
  overflow-x: auto;
  padding-bottom: var(--space-xs); /* scroll indicator space */
}

/* Focus indicators use outline (not box-shadow) due to overflow clipping */
.cast-list__scroll:focus-within {
  outline: none;
}

.cast-list__member {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2xs);
  min-width: 5rem;
  text-align: center;
}

.cast-list__member:focus-visible {
  outline: 0.125rem solid var(--secondary);
  outline-offset: 0.125rem;
}

.cast-list__avatar {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  overflow: hidden;
  background-color: var(--surface-container-high);
  flex-shrink: 0;
}

.cast-list__photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.cast-list__name {
  color: var(--on-surface);
}

.cast-list__character {
  color: var(--tertiary);
}
</style>
