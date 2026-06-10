<script setup lang="ts">
import { telHref, fallbackSiteContacts } from '~/data/siteContacts'
import { useSiteContacts, resolveSiteContacts } from '~/composables/useSiteContent'

const { data: contactsData } = useSiteContacts()
const contacts = computed(() =>
  resolveSiteContacts(contactsData.value?.data?.contacts ?? null, fallbackSiteContacts),
)

useHead({
  title: 'Accessibility — Final Cut',
  meta: [
    { name: 'description', content: 'Accessibility services at Final Cut including wheelchair seating, assisted listening, open captions, audio description, and sensory-friendly screenings.' },
  ],
})
</script>

<template>
  <div class="accessibility-page">
    <div class="close-up">
      <h1 class="accessibility-page__title display-sm">Accessibility</h1>

      <p class="accessibility-page__intro body-lg">
        Final Cut is committed to providing an inclusive experience for every guest. Our facilities, services, and programming are designed so that everyone can enjoy the magic of cinema.
      </p>

      <!-- Assisted Listening Devices -->
      <section class="accessibility-page__section">
        <h2 class="accessibility-page__heading headline-md">Assisted Listening Devices</h2>
        <p class="accessibility-page__text body-md">
          Assistive listening devices are available at no charge from the guest services desk in each lobby. We offer both headset and neck-loop receivers compatible with hearing aids set to T-coil mode. A valid ID is required as a deposit and returned when the device is brought back.
        </p>
      </section>

      <!-- Wheelchair Seating -->
      <section class="accessibility-page__section">
        <h2 class="accessibility-page__heading headline-md">Wheelchair Seating</h2>
        <p class="accessibility-page__text body-md">
          Every auditorium has designated wheelchair-accessible seating locations with companion seats. These seats are integrated into the main seating area — not separated or placed at the back. Accessible seats can be selected during the normal ticket purchase flow and are clearly marked in the seat map.
        </p>
      </section>

      <!-- Open Caption Showtimes -->
      <section class="accessibility-page__section">
        <h2 class="accessibility-page__heading headline-md">Open Caption Showtimes</h2>
        <p class="accessibility-page__text body-md">
          We schedule open caption screenings for most films throughout the week. Captions are displayed directly on the screen so no special equipment is needed. Check our calendar for upcoming open caption showtimes.
        </p>
        <NuxtLink
          to="/whats-on?accessibility=open_caption"
          class="accessibility-page__link body-md"
        >
          View open caption showtimes
        </NuxtLink>
      </section>

      <!-- Audio Description -->
      <section class="accessibility-page__section">
        <h2 class="accessibility-page__heading headline-md">Audio Description</h2>
        <p class="accessibility-page__text body-md">
          Audio description narrates visual elements of the film — actions, facial expressions, scene changes — through a personal headset. Audio description devices are available at guest services for any screening where an audio description track is available.
        </p>
        <NuxtLink
          to="/whats-on?accessibility=audio_described"
          class="accessibility-page__link body-md"
        >
          View audio described showtimes
        </NuxtLink>
      </section>

      <!-- Sensory-Friendly Screenings -->
      <section class="accessibility-page__section">
        <h2 class="accessibility-page__heading headline-md">Sensory-Friendly Screenings</h2>
        <p class="accessibility-page__text body-md">
          Our sensory-friendly screenings offer a modified environment: house lights are kept slightly up, sound levels are reduced, and there are no previews or pre-show advertisements. Guests are welcome to move around and make noise. These screenings are open to everyone and are especially popular with families.
        </p>
        <NuxtLink
          to="/whats-on?accessibility=sensory_friendly"
          class="accessibility-page__link body-md"
        >
          View sensory-friendly screenings
        </NuxtLink>
      </section>

      <!-- Service Animals -->
      <section class="accessibility-page__section">
        <h2 class="accessibility-page__heading headline-md">Service Animals</h2>
        <p class="accessibility-page__text body-md">
          Service animals are welcome in all areas of the theater. We ask that service animals remain on the floor beside their handler during screenings. Fresh water is available upon request from any staff member.
        </p>
      </section>

      <!-- Contact -->
      <section class="accessibility-page__section">
        <h2 class="accessibility-page__heading headline-md">Need Assistance?</h2>
        <p class="accessibility-page__text body-md">
          If you have questions about accessibility or need to arrange accommodations in advance, please contact us. We want to make sure your visit works for you.
        </p>
        <div class="accessibility-page__contact">
          <p class="body-md">
            <strong>Email:</strong>
            <a :href="`mailto:${contacts.accessibilityEmail}`" class="accessibility-page__link">{{ contacts.accessibilityEmail }}</a>
          </p>
          <p class="body-md">
            <strong>Phone:</strong>
            <a :href="telHref(contacts.accessibilityPhone)" class="accessibility-page__link">{{ contacts.accessibilityPhone }}</a>
          </p>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.accessibility-page {
  padding-block: var(--space-3xl);
}

.accessibility-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-md);
}

.accessibility-page__intro {
  color: var(--tertiary);
  margin: 0 0 var(--space-2xl);
  line-height: 1.7;
}

.accessibility-page__section {
  margin-bottom: var(--space-2xl);
}

.accessibility-page__heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-md);
}

.accessibility-page__text {
  color: var(--on-surface);
  margin: 0 0 var(--space-sm);
  line-height: 1.7;
}

.accessibility-page__link {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}

.accessibility-page__contact {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.accessibility-page__contact p {
  margin: 0;
  color: var(--on-surface);
}

.accessibility-page__contact strong {
  color: var(--tertiary);
}
</style>
