<script setup lang="ts">
import type { ShowtimeLocation } from '~/types/showtime'

const props = defineProps<{
  location: ShowtimeLocation
  movieSlug: string
}>()

// Compose the address string from available fields. Defensive — any field
// may be absent if the backend has not yet enriched the seatmap response
// with the full location payload. Only render parts that are present so
// the banner is never visually broken by a missing optional field.
const addressSummary = computed<string>(() => {
  const parts: string[] = []
  if (props.location.street) parts.push(props.location.street)
  if (props.location.city) parts.push(props.location.city)
  return parts.join(', ')
})

const changeLocationHref = computed<string>(() => `/movies/${props.movieSlug}`)
</script>

<template>
  <!--
    BookingLocationBanner — FTC-adjacent explicit venue anchoring.

    This band is the single moment in the purchase flow where the user
    sees and consciously commits to the venue they're booking at. It must
    appear ABOVE the auditorium grid, full-width, using a surface tier
    shift (not a border) to separate it from the seat map.

    Semantics: <aside> conveys supplementary/contextual information about
    the primary page content (the seat map). Role and label follow the
    WAI-ARIA Landmark guidelines — complementary content describing the
    purchase context.

    Mobile collapse: below screen-sm (40rem), the address and phone are
    hidden; only the venue name and the "Change location" link remain
    visible on a single line to preserve banner height.
  -->
  <aside
    class="booking-location-banner"
    aria-label="Booking location"
  >
    <div class="booking-location-banner__inner">
      <span class="booking-location-banner__lead" aria-hidden="true">
        <!-- Location pin icon — decorative, aria-hidden -->
        <svg
          viewBox="0 0 24 24"
          fill="currentColor"
          width="1rem"
          height="1rem"
          aria-hidden="true"
          focusable="false"
        >
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" />
        </svg>
      </span>

      <p class="booking-location-banner__copy">
        <!-- Screen-reader prefix: announce "You're booking at {name}" -->
        <span class="booking-location-banner__label">You're booking at</span>
        <strong class="booking-location-banner__name">{{ location.name }}</strong>

        <template v-if="addressSummary">
          <span class="booking-location-banner__sep" aria-hidden="true"> — </span>
          <span class="booking-location-banner__address">{{ addressSummary }}</span>
        </template>

        <template v-if="location.phone">
          <span class="booking-location-banner__sep booking-location-banner__sep--phone" aria-hidden="true">. </span>
          <span class="booking-location-banner__phone">{{ location.phone }}</span>
        </template>
      </p>

      <NuxtLink
        :to="changeLocationHref"
        class="booking-location-banner__change"
        :aria-label="`Change location — return to ${location.name} showtimes`"
      >
        Change location
      </NuxtLink>
    </div>
  </aside>
</template>

<style scoped>
/* Banner sits on surface-container — a half-step up from the surface (#131313)
   page background. No border — the surface tier shift provides the boundary
   per the "No-Line" rule (DESIGN_SYSTEM.md § No-Line Rule). */
.booking-location-banner {
  background-color: var(--surface-container);
  width: 100%;
}

.booking-location-banner__inner {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  max-width: 90rem;
  margin-inline: auto;
  flex-wrap: wrap;
}

@media (min-width: 40rem) {
  .booking-location-banner__inner {
    padding: var(--space-sm) var(--space-xl);
    flex-wrap: nowrap;
  }
}

@media (min-width: 60rem) {
  .booking-location-banner__inner {
    padding: var(--space-sm) var(--space-2xl);
  }
}

.booking-location-banner__lead {
  color: var(--tertiary);
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.booking-location-banner__copy {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0 var(--space-2xs);
  margin: 0;
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  flex: 1;
  min-width: 0;
}

.booking-location-banner__label {
  /* "You're booking at" prefix — slightly subdued, tertiary weight */
  color: var(--tertiary);
  margin-right: var(--space-2xs);
}

.booking-location-banner__name {
  color: var(--on-surface);
  font-weight: 600;
  font-family: var(--font-display);
  font-size: 0.875rem;
}

.booking-location-banner__sep {
  color: var(--tertiary);
  opacity: 0.5;
}

.booking-location-banner__address {
  color: var(--tertiary);
}

.booking-location-banner__phone {
  color: var(--tertiary);
}

/* On mobile (below screen-sm), collapse address and phone to keep the
   banner to a single compact line. Only name + change-location remain. */
@media (max-width: 39.9375rem) {
  .booking-location-banner__address,
  .booking-location-banner__phone,
  .booking-location-banner__sep {
    display: none;
  }
}

/* The sep before phone gets its own class so the mobile hide targets only
   the phone-adjacent separator, not the one after location name. */
.booking-location-banner__sep--phone {
  /* Inherits the base .booking-location-banner__sep rules;
     the @media block above hides all seps on mobile. */
}

.booking-location-banner__change {
  margin-left: auto;
  flex-shrink: 0;
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--secondary);
  text-decoration: none;
  letter-spacing: 0.04em;
  white-space: nowrap;
  padding: var(--space-2xs) var(--space-xs);
  /* Gold underline on hover — tertiary button pattern from DESIGN_SYSTEM */
  position: relative;
}

.booking-location-banner__change::after {
  content: '';
  position: absolute;
  left: 50%;
  right: 50%;
  bottom: 0;
  height: 1px;
  background-color: var(--secondary);
  transition: left 0.25s cubic-bezier(0.2, 0.0, 0.0, 1.0),
              right 0.25s cubic-bezier(0.2, 0.0, 0.0, 1.0);
}

.booking-location-banner__change:hover::after,
.booking-location-banner__change:focus-visible::after {
  left: var(--space-xs);
  right: var(--space-xs);
}

.booking-location-banner__change:focus-visible {
  /* Double-ring focus indicator per DESIGN_SYSTEM_STRUCTURE.md § 7 */
  outline: none;
  box-shadow: 0 0 0 0.125rem var(--surface-container), 0 0 0 0.25rem var(--secondary);
  border-radius: 0.125rem;
}
</style>
