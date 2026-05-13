<script setup lang="ts">
import type { Booking } from '~/types/booking'

defineProps<{
  bookings: Booking[]
}>()

function seatsSummary(seats: Booking['seats']): string {
  return seats.map(s => s.label).join(', ')
}
</script>

<template>
  <div class="upcoming-bookings">
    <div v-if="bookings.length === 0" class="upcoming-bookings__empty">
      <p class="upcoming-bookings__empty-text body-md">No upcoming bookings.</p>
      <CvButton variant="secondary" href="/movies">
        Browse Movies
      </CvButton>
    </div>

    <div v-else class="upcoming-bookings__list">
      <CvCard
        v-for="booking in bookings"
        :key="booking.id"
        interactive
        :href="`/purchase/confirmation/${booking.id}`"
        class="upcoming-bookings__card"
      >
        <div class="upcoming-bookings__content">
          <div class="upcoming-bookings__poster">
            <img
              v-if="booking.moviePosterUrl"
              :src="booking.moviePosterUrl"
              :alt="`${booking.movieTitle} poster`"
              class="upcoming-bookings__poster-img"
              loading="lazy"
            />
            <div v-else class="upcoming-bookings__poster-fallback" />
          </div>

          <div class="upcoming-bookings__info">
            <h3 class="upcoming-bookings__title headline-sm">{{ booking.movieTitle }}</h3>
            <p class="upcoming-bookings__datetime body-md">{{ formatDateTime(booking.startTime) }}</p>
            <p class="upcoming-bookings__screen body-sm">{{ booking.screenName }}</p>
            <p class="upcoming-bookings__seats label-md">Seats: {{ seatsSummary(booking.seats) }}</p>
          </div>
        </div>
      </CvCard>
    </div>
  </div>
</template>

<style scoped>
.upcoming-bookings {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.upcoming-bookings__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-xl);
  text-align: center;
}

.upcoming-bookings__empty-text {
  color: var(--tertiary);
  margin: 0;
}

.upcoming-bookings__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.upcoming-bookings__content {
  display: flex;
  gap: var(--space-md);
}

.upcoming-bookings__poster {
  flex-shrink: 0;
  width: 5rem;
  aspect-ratio: 2 / 3;
  border-radius: 0.125rem;
  overflow: hidden;
  background-color: var(--surface-container-lowest);
}

.upcoming-bookings__poster-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
  display: block;
}

.upcoming-bookings__poster-fallback {
  width: 100%;
  height: 100%;
  background-color: var(--surface-container-lowest);
}

.upcoming-bookings__info {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  min-width: 0;
}

.upcoming-bookings__title {
  color: var(--on-surface);
  margin: 0;
}

.upcoming-bookings__datetime {
  color: var(--secondary);
  margin: 0;
}

.upcoming-bookings__screen {
  color: var(--tertiary);
  margin: 0;
}

.upcoming-bookings__seats {
  color: var(--tertiary);
  margin: 0;
}
</style>
