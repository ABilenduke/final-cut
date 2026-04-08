<script setup lang="ts">
import type { CalendarEvent } from '~/types/calendar-event'

defineProps<{
  event: CalendarEvent & {
    pricing?: string
    includes?: string[]
  }
}>()

const accessibilityLabels: Record<string, string> = {
  sensory_friendly: 'Sensory Friendly',
  open_caption: 'Open Captions',
  audio_described: 'Audio Described',
}
</script>

<template>
  <article class="event-detail">
    <h1 class="event-detail__title display-sm">{{ event.title }}</h1>

    <div class="event-detail__meta">
      <time :datetime="event.startTime" class="event-detail__datetime body-lg">
        {{ formatDate(event.date) }}
        <template v-if="event.startTime"> &middot; {{ formatTime(event.startTime) }}</template>
        <template v-if="event.endTime"> &ndash; {{ formatTime(event.endTime) }}</template>
      </time>
    </div>

    <div v-if="event.accessibilityTags.length > 0" class="event-detail__tags">
      <CvBadge
        v-for="tag in event.accessibilityTags"
        :key="tag"
        variant="warning"
        size="sm"
      >
        {{ accessibilityLabels[tag] || tag }}
      </CvBadge>
    </div>

    <div v-if="event.loyaltyOnly" class="event-detail__loyalty">
      <CvBadge variant="accent">Members Only</CvBadge>
    </div>

    <div class="event-detail__body body-md">
      <p>{{ event.description }}</p>
    </div>

    <div v-if="event.includes && event.includes.length > 0" class="event-detail__includes">
      <h2 class="event-detail__section-heading headline-sm">What's Included</h2>
      <ul class="event-detail__includes-list">
        <li v-for="item in event.includes" :key="item" class="body-md">{{ item }}</li>
      </ul>
    </div>

    <div v-if="event.pricing" class="event-detail__pricing">
      <h2 class="event-detail__section-heading headline-sm">Pricing</h2>
      <p class="body-md">{{ event.pricing }}</p>
    </div>

    <div class="event-detail__actions">
      <CvButton
        v-if="event.ticketUrl"
        :href="event.ticketUrl"
      >
        Get Tickets
      </CvButton>
      <CvButton v-else variant="secondary">
        RSVP
      </CvButton>
    </div>
  </article>
</template>

<style scoped>
.event-detail {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.event-detail__title {
  color: var(--on-surface);
  margin: 0;
}

.event-detail__meta {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.event-detail__datetime {
  color: var(--secondary);
}

.event-detail__tags {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
}

.event-detail__loyalty {
  display: flex;
}

.event-detail__body {
  color: var(--on-surface);
}

.event-detail__body p {
  margin: 0;
  line-height: 1.6;
}

.event-detail__section-heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-sm) 0;
}

.event-detail__includes-list {
  margin: 0;
  padding-left: var(--space-lg);
  color: var(--on-surface);
}

.event-detail__includes-list li {
  margin-bottom: var(--space-xs);
}

.event-detail__pricing {
  color: var(--on-surface);
}

.event-detail__pricing p {
  margin: 0;
}

.event-detail__actions {
  display: flex;
  gap: var(--space-md);
}
</style>
