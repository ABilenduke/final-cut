<script setup lang="ts">
import { absoluteUrl, eventSchema } from '~/utils/seo'

const route = useRoute()
const slug = route.params.slug as string

const { getEvent } = useCalendarEvents()
const { data: eventData, error } = getEvent(slug)

const event = computed(() => eventData.value?.data ?? null)

const siteUrl = String(useRuntimeConfig().public.siteUrl ?? '')

// SEO — title (branded by the global titleTemplate), OG/Twitter, canonical, and
// a schema.org Event with a Place when the event is venue-scoped. Reactive
// getter so the tags fill in once the async fetch resolves.
useSeo(() => {
  const e = event.value
  const path = `/events/${slug}`
  return {
    title: e?.title ?? 'Event',
    description: e?.description || 'Event details at Final Cut.',
    path,
    image: e?.imageUrl,
    jsonLd: e
      ? eventSchema({
          name: e.title,
          startDate: e.startTime,
          endDate: e.endTime,
          description: e.description,
          image: e.imageUrl,
          url: absoluteUrl(path, siteUrl),
          ticketUrl: e.ticketUrl,
          location: e.location ?? null,
          siteUrl,
        })
      : null,
  }
})
</script>

<template>
  <div class="event-detail-page">
    <!-- Error state -->
    <div v-if="error" class="container">
      <div class="event-detail-page__error close-up">
        <h1 class="headline-lg">Event Not Found</h1>
        <p class="body-md">The event you're looking for doesn't exist or has been removed.</p>
        <CvButton href="/events">Back to Events</CvButton>
      </div>
    </div>

    <template v-else-if="event">
      <!-- Wide Frame hero -->
      <div v-if="event.imageUrl" class="event-detail-page__hero wide-frame">
        <div class="event-detail-page__hero-inner">
          <img
            :src="event.imageUrl"
            :alt="event.title"
            class="event-detail-page__hero-image"
          />
          <div class="event-detail-page__hero-overlay" />
        </div>
      </div>

      <!-- Close-Up body -->
      <div class="container">
        <div class="close-up">
          <EventDetail :event="event" />
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.event-detail-page {
  padding-block: var(--space-3xl);
}

.event-detail-page__error {
  text-align: center;
  padding: var(--space-3xl) 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-lg);
  color: var(--on-surface);
}

.event-detail-page__hero {
  margin-bottom: var(--space-2xl);
}

.event-detail-page__hero-inner {
  position: relative;
  max-height: 25rem;
  overflow: hidden;
}

.event-detail-page__hero-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.event-detail-page__hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    var(--surface) 0%,
    color-mix(in srgb, var(--surface) 60%, transparent) 30%,
    transparent 100%
  );
}
</style>
