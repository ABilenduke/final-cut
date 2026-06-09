<script setup lang="ts">
import { hashToHue } from '~/utils/posterFallback'

const { getEvents } = useCalendarEvents()

// Fetch special events for current and next month
const now = new Date()
const currentMonth = now.getMonth() + 1
const currentYear = now.getFullYear()
const nextMonth = currentMonth === 12 ? 1 : currentMonth + 1
const nextYear = currentMonth === 12 ? currentYear + 1 : currentYear

const { data: currentMonthData } = getEvents(currentMonth, currentYear, 'special_event')
const { data: nextMonthData } = getEvents(nextMonth, nextYear, 'special_event')

const allEvents = computed(() => {
  const current = currentMonthData.value?.data ?? []
  const next = nextMonthData.value?.data ?? []
  return [...current, ...next].sort((a, b) => a.startTime.localeCompare(b.startTime))
})

const featuredEvent = computed(() => allEvents.value[0] ?? null)
const upcomingEvents = computed(() => allEvents.value.slice(1))

// Branded fallback for the featured hero when its seeded banner has no binary.
const featuredImgFailed = ref(false)
const showFeaturedImage = computed(
  () => Boolean(featuredEvent.value?.imageUrl) && !featuredImgFailed.value,
)
const featuredHue = computed(() => hashToHue(featuredEvent.value?.title ?? ''))

// SEO
useHead({
  title: 'Events — Final Cut',
  meta: [
    { name: 'description', content: 'Special events, screenings, and experiences at Final Cut.' },
    { property: 'og:title', content: 'Events — Final Cut' },
    { property: 'og:description', content: 'Special events, screenings, and experiences at Final Cut.' },
    { property: 'og:type', content: 'website' },
  ],
})
</script>

<template>
  <div class="events-page">
    <!-- Featured event (Wide Frame) -->
    <div v-if="featuredEvent" class="events-page__featured wide-frame">
      <div class="events-page__featured-inner">
        <div class="events-page__featured-bg">
          <img
            v-if="showFeaturedImage"
            :src="featuredEvent.imageUrl"
            :alt="featuredEvent.title"
            class="events-page__featured-image"
            @error="featuredImgFailed = true"
          />
          <div
            v-else
            class="events-page__featured-fallback"
            :style="{ '--fallback-hue': featuredHue }"
            aria-hidden="true"
          />
          <div class="events-page__featured-overlay" />
        </div>
        <div class="events-page__featured-content container">
          <CvBadge>{{ formatDate(featuredEvent.date) }}</CvBadge>
          <h2 class="events-page__featured-title display-sm">{{ featuredEvent.title }}</h2>
          <p v-if="featuredEvent.description" class="events-page__featured-desc body-lg">
            {{ featuredEvent.description }}
          </p>
          <CvButton
            v-if="featuredEvent.slug"
            :href="`/events/${featuredEvent.slug}`"
          >
            Learn More
          </CvButton>
        </div>
      </div>
    </div>

    <!-- Upcoming events (Ensemble) -->
    <div class="container">
      <h2 class="events-page__section-heading headline-lg">Upcoming Events</h2>

      <div v-if="upcomingEvents.length > 0" class="ensemble">
        <EventListCard
          v-for="event in upcomingEvents"
          :key="event.id"
          :event="event"
        />
      </div>

      <p v-else class="events-page__empty body-md">
        No upcoming events at this time.
      </p>
    </div>
  </div>
</template>

<style scoped>
.events-page {
  padding-block: var(--space-3xl);
}

.events-page__featured {
  margin-bottom: var(--space-3xl);
}

.events-page__featured-inner {
  position: relative;
  min-height: 25rem;
  display: flex;
  align-items: flex-end;
}

.events-page__featured-bg {
  position: absolute;
  inset: 0;
}

.events-page__featured-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.events-page__featured-fallback {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    hsl(var(--fallback-hue, 0) 40% 22%),
    hsl(var(--fallback-hue, 0) 30% 8%)
  );
}

.events-page__featured-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    var(--surface) 0%,
    color-mix(in srgb, var(--surface) 80%, transparent) 40%,
    transparent 100%
  );
}

.events-page__featured-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding-block: var(--space-2xl);
}

.events-page__featured-title {
  color: var(--on-surface);
  margin: 0;
}

.events-page__featured-desc {
  color: var(--tertiary);
  margin: 0;
  max-width: 40rem;
}

.events-page__section-heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-xl) 0;
}

.events-page__empty {
  color: var(--tertiary);
  text-align: center;
  padding: var(--space-3xl) 0;
}
</style>
