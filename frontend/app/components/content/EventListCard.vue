<script setup lang="ts">
import { computed, ref } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'
import { hashToHue, initialsFrom } from '~/utils/posterFallback'

const props = defineProps<{
  event: CalendarEvent
}>()

// Branded fallback: a seeded image_path may have no on-disk binary (provisioned
// via upload/CDN). On null or load failure, degrade to a hashed-hue gradient +
// glyph instead of a broken-image icon.
const imgFailed = ref(false)
const showImage = computed(() => Boolean(props.event.imageUrl) && !imgFailed.value)
const fallbackHue = computed(() => hashToHue(props.event.title))
const fallbackMark = computed(() => initialsFrom(props.event.title))

function truncate(text: string, maxLength: number): string {
  if (text.length <= maxLength) return text
  return text.slice(0, maxLength).trimEnd() + '...'
}

const typeLabels: Record<string, string> = {
  showtime: 'Showtime',
  special_event: 'Special Event',
  loyalty_exclusive: 'Loyalty Exclusive',
  private_screening_blackout: 'Private Screening',
}
</script>

<template>
  <CvCard
    class="event-list-card"
    interactive
    :href="event.slug ? `/events/${event.slug}` : undefined"
  >
    <template #header>
      <div class="event-list-card__image-container">
        <img
          v-if="showImage"
          :src="event.imageUrl"
          :alt="event.title"
          class="event-list-card__image"
          loading="lazy"
          @error="imgFailed = true"
        />
        <div
          v-else
          class="event-list-card__image-fallback"
          :style="{ '--fallback-hue': fallbackHue }"
          aria-hidden="true"
        >
          <span class="event-list-card__image-mark">{{ fallbackMark }}</span>
        </div>
      </div>
    </template>

    <div class="event-list-card__content">
      <div class="event-list-card__badges">
        <CvBadge size="sm">{{ formatDate(event.date) }}</CvBadge>
        <CvBadge size="sm">{{ typeLabels[event.type] || event.type }}</CvBadge>
        <CvBadge v-if="event.loyaltyOnly" variant="accent" size="sm">Members Only</CvBadge>
      </div>
      <h3 class="event-list-card__title headline-sm">{{ event.title }}</h3>
      <p v-if="event.description" class="event-list-card__description body-sm">
        {{ truncate(event.description, 120) }}
      </p>
    </div>

    <template #footer>
      <span class="event-list-card__link label-lg">Learn More</span>
    </template>
  </CvCard>
</template>

<style scoped>
.event-list-card__image-container {
  aspect-ratio: 4 / 3;
  background-color: var(--surface-container-lowest);
  border-radius: 0.125rem;
  overflow: hidden;
  margin-bottom: var(--space-sm);
}

.event-list-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.event-list-card__image-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
  background: linear-gradient(
    135deg,
    hsl(var(--fallback-hue, 0) 40% 22%),
    hsl(var(--fallback-hue, 0) 30% 8%)
  );
}

.event-list-card__image-mark {
  font-family: var(--font-display);
  font-weight: 600;
  font-size: 1.5rem;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  opacity: 0.85;
  padding: var(--space-sm);
  text-shadow: 0 0 0.75rem rgb(0 0 0 / 0.6);
}

.event-list-card__content {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.event-list-card__badges {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
}

.event-list-card__title {
  color: var(--on-surface);
  margin: 0;
}

.event-list-card__description {
  color: var(--tertiary);
  margin: 0;
  line-height: 1.5;
}

.event-list-card__link {
  color: var(--secondary);
}
</style>
