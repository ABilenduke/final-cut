<script setup lang="ts">
import { usePublicLocations } from '~/composables/usePublicLocations'
import { useContactInfo, resolveContactInfo } from '~/composables/useSiteContent'
import type { WeekdayKey } from '~/types/location'

// Venue data is admin-managed via LocationResource (hours/phone/email/address).
const { fetchLocations } = usePublicLocations()
const { data: locationsData } = fetchLocations()
const locations = computed(() => locationsData.value?.data ?? [])
const primary = computed(() => locations.value[0])

// Built-in "getting here" prose — also the fallback when no admin edit
// exists yet (admin-v6 G6).
const FALLBACK_CONTACT_INFO = {
  byCar: 'Parking garage located directly beneath the theater. Enter via Cinema Lane. First 3 hours validated with ticket purchase.',
  byTransit: 'Nearest subway: Cinema Station (A/C/E lines), one block east. Bus routes 14, 23, and 42 stop at Cinema Boulevard.',
  accessibility: 'Step-free access at all entrances. Accessible parking spaces available on Level 1 of the garage, nearest to the elevator. Wheelchair-accessible seating available in every auditorium.',
}

const { data: contactInfoData } = useContactInfo()
const contactInfo = computed(() =>
  resolveContactInfo(contactInfoData.value?.data?.contactInfo ?? null, FALLBACK_CONTACT_INFO),
)

const DAY_LABELS: Array<[WeekdayKey, string]> = [
  ['monday', 'Monday'],
  ['tuesday', 'Tuesday'],
  ['wednesday', 'Wednesday'],
  ['thursday', 'Thursday'],
  ['friday', 'Friday'],
  ['saturday', 'Saturday'],
  ['sunday', 'Sunday'],
]

function formatHour(value: string): string {
  const [h = 0, m = 0] = value.split(':').map(Number)
  const suffix = h >= 12 ? 'PM' : 'AM'
  const hour12 = h % 12 === 0 ? 12 : h % 12
  return `${hour12}:${String(m).padStart(2, '0')} ${suffix}`
}

useHead(() => ({
  title: 'Visit Us — Final Cut',
  meta: [
    { name: 'description', content: 'Find directions, hours, and contact information for Final Cut theater.' },
    { property: 'og:title', content: 'Visit Us — Final Cut' },
    { property: 'og:description', content: 'Find directions, hours, and contact information for Final Cut theater.' },
    { property: 'og:type', content: 'website' },
  ],
  script: locations.value.map(location => ({
    type: 'application/ld+json',
    innerHTML: safeJsonLd({
      '@context': 'https://schema.org',
      '@type': 'LocalBusiness',
      name: `Final Cut — ${location.name}`,
      description: 'A cinematic movie theater experience.',
      address: {
        '@type': 'PostalAddress',
        streetAddress: location.street,
        addressLocality: location.city,
        addressRegion: location.state,
        postalCode: location.postal_code,
        addressCountry: location.country,
      },
      telephone: location.phone,
      email: location.email,
    }),
  })),
}))
</script>

<template>
  <div class="contact-page">
    <div class="container">
      <h1 class="contact-page__title display-sm">Visit Us</h1>
      <div class="establishing-shot">
        <div class="establishing-shot__primary">
          <ContactMap
            v-if="primary?.latitude != null && primary?.longitude != null"
            :coordinates="{ lat: primary.latitude, lng: primary.longitude }"
            :address="primary.address"
          />

          <section class="contact-page__info">
            <h2 class="headline-sm">Getting Here</h2>
            <div class="contact-page__directions">
              <div>
                <h3 class="title-lg">By Car</h3>
                <p class="body-md">{{ contactInfo.byCar }}</p>
              </div>
              <div>
                <h3 class="title-lg">By Transit</h3>
                <p class="body-md">{{ contactInfo.byTransit }}</p>
              </div>
            </div>
          </section>

          <section class="contact-page__accessibility-info">
            <h2 class="headline-sm">Accessibility</h2>
            <p class="body-md">{{ contactInfo.accessibility }}</p>
          </section>
        </div>

        <div class="establishing-shot__secondary">
          <section
            v-for="location in locations"
            :key="location.slug"
            class="contact-page__hours"
          >
            <h2 class="headline-sm">{{ locations.length > 1 ? `Hours — ${location.name}` : 'Hours' }}</h2>
            <dl class="contact-page__hours-list">
              <div
                v-for="[day, label] in DAY_LABELS"
                :key="day"
                class="contact-page__hours-row"
              >
                <dt class="body-md">{{ label }}</dt>
                <dd class="body-md">
                  <template v-if="location.hours?.[day]">
                    {{ formatHour(location.hours[day]!.open) }} – {{ formatHour(location.hours[day]!.close) }}
                  </template>
                  <template v-else>Closed</template>
                </dd>
              </div>
            </dl>
            <p class="body-md"><strong>Phone:</strong> {{ location.phone }}</p>
            <p class="body-md"><strong>Email:</strong> {{ location.email }}</p>
          </section>

          <ContactForm />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.contact-page {
  padding: var(--space-3xl) 0;
}

.contact-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-2xl);
}

.contact-page__info,
.contact-page__accessibility-info,
.contact-page__hours,
.contact-page__details {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.contact-page__info h2,
.contact-page__accessibility-info h2,
.contact-page__hours h2,
.contact-page__details h2 {
  color: var(--on-surface);
  margin: 0;
}

.contact-page__info h3 {
  color: var(--secondary);
  margin: 0;
}

.contact-page__info p,
.contact-page__accessibility-info p,
.contact-page__details p {
  color: var(--tertiary);
  margin: 0;
  line-height: 1.6;
}

.contact-page__directions {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.contact-page__hours-list {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.contact-page__hours-row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-md);
}

.contact-page__hours-row dt {
  color: var(--tertiary);
}

.contact-page__hours-row dd {
  color: var(--on-surface);
  margin: 0;
}

.establishing-shot__primary {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.establishing-shot__secondary {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}
</style>
