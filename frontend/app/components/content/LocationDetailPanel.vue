<script setup lang="ts">
import type { Location, WeekdayKey } from '~/types/location'
import { locationMapsUrl } from '~/utils/locationMapsUrl'

const props = defineProps<{
  location: Location
}>()

const ORDERED_DAYS: WeekdayKey[] = [
  'monday',
  'tuesday',
  'wednesday',
  'thursday',
  'friday',
  'saturday',
  'sunday',
]

const DAY_LABELS: Record<WeekdayKey, string> = {
  monday: 'Monday',
  tuesday: 'Tuesday',
  wednesday: 'Wednesday',
  thursday: 'Thursday',
  friday: 'Friday',
  saturday: 'Saturday',
  sunday: 'Sunday',
}

/** Check whether the hours object has at least one entry to render */
const hasHours = computed<boolean>(() =>
  ORDERED_DAYS.some(day => day in (props.location.hours ?? {})),
)

const mapsUrl = computed<string | null>(() => locationMapsUrl(props.location))

// Detail panel renders the structured address + appends country for non-US
// venues. The backend's `address` field (street, city, state postal_code)
// is the base; country is appended only when relevant.
const fullAddress = computed<string>(() => {
  const base = props.location.address
  const { country } = props.location
  return country && country !== 'US' ? `${base}, ${country}` : base
})
</script>

<template>
  <div class="location-detail-panel">

    <!-- ——— Contact & Address ——— -->
    <section class="location-detail-panel__section" aria-labelledby="lp-contact-heading">
      <h2 id="lp-contact-heading" class="location-detail-panel__section-title">Visit Us</h2>

      <address class="location-detail-panel__address">
        <p class="location-detail-panel__address-line">{{ fullAddress }}</p>

        <p v-if="location.phone" class="location-detail-panel__contact-row">
          <span class="location-detail-panel__contact-label">Phone</span>
          <a
            :href="`tel:${location.phone}`"
            class="location-detail-panel__link"
          >{{ location.phone }}</a>
        </p>

        <p v-if="location.email" class="location-detail-panel__contact-row">
          <span class="location-detail-panel__contact-label">Email</span>
          <a
            :href="`mailto:${location.email}`"
            class="location-detail-panel__link"
          >{{ location.email }}</a>
        </p>
      </address>

      <div class="location-detail-panel__map-cta">
        <CvButton
          v-if="mapsUrl"
          variant="primary"
          :href="mapsUrl"
          target="_blank"
          rel="noopener noreferrer"
          :aria-label="`Get directions to ${location.name}`"
        >
          Get Directions
        </CvButton>
        <CvButton
          v-if="location.phone"
          variant="secondary"
          :href="`tel:${location.phone}`"
        >
          Call
        </CvButton>
      </div>
    </section>

    <!-- ——— Hours of Operation ——— -->
    <section
      v-if="hasHours"
      class="location-detail-panel__section"
      aria-labelledby="lp-hours-heading"
    >
      <h2 id="lp-hours-heading" class="location-detail-panel__section-title">Hours</h2>

      <table class="location-detail-panel__hours-table">
        <tbody>
          <tr
            v-for="day in ORDERED_DAYS"
            :key="day"
            class="location-detail-panel__hours-row"
          >
            <th
              class="location-detail-panel__hours-day"
              scope="row"
            >{{ DAY_LABELS[day] }}</th>
            <td class="location-detail-panel__hours-time">
              <template v-if="location.hours[day]">
                {{ location.hours[day]!.open }} – {{ location.hours[day]!.close }}
              </template>
              <template v-else>
                <span class="location-detail-panel__hours-closed">Closed</span>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- ——— Accessibility ——— -->
    <section class="location-detail-panel__section" aria-labelledby="lp-access-heading">
      <h2 id="lp-access-heading" class="location-detail-panel__section-title">Accessibility</h2>
      <ul class="location-detail-panel__list">
        <li>Wheelchair-accessible entrances and restrooms</li>
        <li>Companion seating available in all auditoriums</li>
        <li>Assisted listening devices available at the box office</li>
        <li>
          <NuxtLink to="/whats-on?accessibility=sensory_friendly" class="location-detail-panel__link">
            Sensory-friendly screenings
          </NuxtLink>
          — see the calendar for upcoming sessions
        </li>
        <li>
          <NuxtLink to="/whats-on?accessibility=open_caption" class="location-detail-panel__link">
            Open-caption screenings
          </NuxtLink>
          — see the calendar for upcoming sessions
        </li>
      </ul>
      <p class="location-detail-panel__note">
        Need specific accommodations? <NuxtLink to="/contact" class="location-detail-panel__link">Contact us</NuxtLink> and we will do our best to help.
      </p>
    </section>

    <!-- ——— Parking ——— -->
    <section class="location-detail-panel__section" aria-labelledby="lp-parking-heading">
      <h2 id="lp-parking-heading" class="location-detail-panel__section-title">Parking &amp; Transit</h2>
      <p class="location-detail-panel__body">
        Street parking is available in the surrounding blocks. Several paid parking garages are within a short walk. Public transit stops are located nearby — check your local transit authority for routes and schedules.
      </p>
    </section>

  </div>
</template>

<style scoped>
.location-detail-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

/* ——— Section ——— */
.location-detail-panel__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.location-detail-panel__section-title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm, 1.5rem);
  font-weight: 500;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin: 0;
  padding-bottom: var(--space-sm);
  border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
}

/* ——— Address ——— */
.location-detail-panel__address {
  font-style: normal;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.location-detail-panel__address-line {
  font-family: var(--font-body);
  font-size: var(--type-body-md, 1rem);
  color: var(--on-surface);
  margin: 0;
}

.location-detail-panel__contact-row {
  display: flex;
  gap: var(--space-md);
  align-items: baseline;
  margin: 0;
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
}

.location-detail-panel__contact-label {
  color: var(--tertiary);
  min-width: 3.5rem;
  font-size: var(--type-label-md, 0.75rem);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.location-detail-panel__link {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.2em;
  transition: color var(--duration-micro) var(--ease-standard);
}

.location-detail-panel__link:hover {
  color: var(--secondary-hover);
}

.location-detail-panel__link:focus-visible {
  outline: var(--border-thick) solid var(--secondary);
  outline-offset: 0.125rem;
  border-radius: var(--radius-sm);
}

/* ——— Map CTA row ——— */
.location-detail-panel__map-cta {
  display: flex;
  gap: var(--space-sm);
  flex-wrap: wrap;
  margin-top: var(--space-sm);
}

/* ——— Hours table ——— */
.location-detail-panel__hours-table {
  width: 100%;
  border-collapse: collapse;
}

.location-detail-panel__hours-row {
  border-bottom: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.15);
}

.location-detail-panel__hours-row:last-child {
  border-bottom: none;
}

.location-detail-panel__hours-day {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--tertiary);
  font-weight: 400;
  text-align: left;
  padding: var(--space-sm) 0;
  width: 8rem;
}

.location-detail-panel__hours-time {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--on-surface);
  text-align: left;
  padding: var(--space-sm) 0;
}

.location-detail-panel__hours-closed {
  color: var(--tertiary);
  font-style: italic;
}

/* ——— Lists & body text ——— */
.location-detail-panel__list {
  padding-left: var(--space-md);
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--tertiary);
  line-height: 1.6;
}

.location-detail-panel__note {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--tertiary);
  margin: 0;
}

.location-detail-panel__body {
  font-family: var(--font-body);
  font-size: var(--type-body-sm, 0.875rem);
  color: var(--tertiary);
  line-height: 1.6;
  margin: 0;
}
</style>
