<script setup lang="ts">
import type { Location } from '~/types/location'
import { locationMapsUrl } from '~/utils/locationMapsUrl'

const props = defineProps<{
  location: Location
  /** Optional distance string (e.g. "2.3 mi away") — rendered only after
   *  geolocation is granted; omit for SSR output. */
  distanceLabel?: string | null
}>()

const mapsUrl = computed<string | null>(() => locationMapsUrl(props.location))
</script>

<template>
  <article class="location-card">
    <!-- The whole card is a link to the venue detail page -->
    <NuxtLink :to="`/locations/${location.slug}`" class="location-card__link" :aria-label="`${location.name} — view venue details`">
      <!-- Venue photo / placeholder -->
      <div class="location-card__image-wrap" aria-hidden="true">
        <img
          v-if="location.imageUrl"
          :src="location.imageUrl"
          :alt="`${location.name} exterior`"
          class="location-card__image"
          loading="lazy"
          width="400"
          height="225"
        />
        <div v-else class="location-card__image-placeholder" aria-hidden="true">
          <span class="location-card__image-glyph">{{ location.name.charAt(0) }}</span>
        </div>
      </div>

      <div class="location-card__body">
        <div class="location-card__header">
          <h2 class="location-card__name">{{ location.name }}</h2>
          <!-- Post-hydration distance caption (not present in SSR HTML) -->
          <span v-if="distanceLabel" class="location-card__distance">{{ distanceLabel }}</span>
        </div>

        <address class="location-card__address">{{ location.address }}</address>

        <p v-if="location.phone" class="location-card__phone">
          <!-- tel: link is interactive — stopPropagation keeps it separate from the card link -->
          <a
            :href="`tel:${location.phone}`"
            class="location-card__phone-link"
            @click.stop
          >{{ location.phone }}</a>
        </p>
      </div>
    </NuxtLink>

    <!-- Action row: outside the card link so they are independently focusable -->
    <div class="location-card__actions">
      <CvButton
        variant="primary"
        :href="`/movies?location=${location.slug}`"
      >
        See Showtimes
      </CvButton>

      <CvButton
        v-if="mapsUrl"
        variant="tertiary"
        :href="mapsUrl"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="`Get directions to ${location.name}`"
      >
        Get Directions
      </CvButton>
    </div>
  </article>
</template>

<style scoped>
.location-card {
  background-color: var(--surface-container-low);
  border-radius: var(--radius-card);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform var(--duration-standard) var(--ease-standard);
}

.location-card:hover {
  transform: translateY(-0.125rem);
}

/* Card-level link wraps the image + body but NOT the action row */
.location-card__link {
  display: flex;
  flex-direction: column;
  flex: 1;
  text-decoration: none;
  color: inherit;
}

.location-card__link:focus-visible {
  outline: var(--border-thick) solid var(--secondary);
  outline-offset: 0.125rem;
}

/* ——— Image ——— */
.location-card__image-wrap {
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background-color: var(--surface-container);
}

.location-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.location-card__image-placeholder {
  width: 100%;
  height: 100%;
  background: radial-gradient(
    ellipse at 35% 40%,
    rgba(var(--primary-container-rgb), 0.45),
    var(--surface-container-lowest)
  );
  display: grid;
  place-items: center;
}

.location-card__image-glyph {
  font-family: var(--font-display);
  font-style: italic;
  font-size: 5rem;
  line-height: 1;
  color: rgba(255, 255, 255, 0.08);
  letter-spacing: -0.04em;
}

/* ——— Body ——— */
.location-card__body {
  padding: var(--space-md);
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  flex: 1;
}

.location-card__header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

.location-card__name {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm, 1.5rem);
  font-weight: 500;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin: 0;
}

.location-card__distance {
  font-family: var(--font-body);
  font-size: var(--type-label-md, 0.75rem);
  color: var(--secondary);
  white-space: nowrap;
}

.location-card__address {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--tertiary);
  font-style: normal;
  line-height: 1.5;
}

.location-card__phone {
  margin: 0;
}

.location-card__phone-link {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--on-surface);
  text-decoration: none;
  transition: color var(--duration-micro) var(--ease-standard);
}

.location-card__phone-link:hover {
  color: var(--secondary);
  text-decoration: underline;
}

.location-card__phone-link:focus-visible {
  outline: var(--border-thick) solid var(--secondary);
  outline-offset: 0.125rem;
  border-radius: var(--radius-sm);
}

/* ——— Action row ——— */
.location-card__actions {
  display: flex;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md) var(--space-md);
  flex-wrap: wrap;
}

</style>
