<script setup lang="ts">
import { fallbackSiteContacts } from '~/data/siteContacts'
import { useSiteContacts, resolveSiteContacts } from '~/composables/useSiteContent'

const { data: contactsData } = useSiteContacts()
const contacts = computed(() =>
  resolveSiteContacts(contactsData.value?.data?.contacts ?? null, fallbackSiteContacts),
)

useHead({
  title: 'Terms — Final Cut',
  meta: [
    { name: 'description', content: 'Terms for using Final Cut services, booking tickets, attending screenings, and purchasing food, drinks, or gift cards.' },
  ],
})
</script>

<template>
  <main class="legal-page">
    <div class="close-up">
      <p class="legal-page__eyebrow">Final Cut</p>
      <h1 class="legal-page__title display-sm">Terms</h1>
      <p class="legal-page__updated body-sm">Last updated May 3, 2026</p>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Tickets and Reservations</h2>
        <p class="legal-page__text body-md">
          Tickets are valid only for the film, auditorium, date, and time shown at checkout. Seats are held after payment confirmation. Please arrive with enough time for ticket scanning and concessions pickup.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Refunds and Exchanges</h2>
        <p class="legal-page__text body-md">
          Refund and exchange requests must be made before showtime. Special events, series passes, and private bookings may have separate refund terms shown during purchase.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Cinema Conduct</h2>
        <p class="legal-page__text body-md">
          Guests are expected to follow staff direction, respect other patrons, and comply with film ratings, accessibility policies, and posted venue rules. We may refuse service or remove guests whose conduct disrupts the screening.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Online Services</h2>
        <p class="legal-page__text body-md">
          Account, checkout, loyalty, and payment features are provided as-is during development. Keep your account credentials private and contact us if you believe your account has been accessed without permission.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Contact</h2>
        <p class="legal-page__text body-md">
          Questions about these terms can be sent to <a :href="`mailto:${contacts.generalEmail}`">{{ contacts.generalEmail }}</a>.
        </p>
      </section>
    </div>
  </main>
</template>

<style scoped>
.legal-page {
  padding-block: var(--space-3xl);
}

.legal-page__eyebrow {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--secondary);
  letter-spacing: 0.18em;
  margin: 0 0 var(--space-sm);
  text-transform: uppercase;
}

.legal-page__title {
  color: var(--on-surface);
  margin: 0;
}

.legal-page__updated {
  color: var(--tertiary);
  margin: var(--space-xs) 0 var(--space-2xl);
}

.legal-page__section {
  margin-bottom: var(--space-2xl);
}

.legal-page__heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-sm);
}

.legal-page__text {
  color: var(--on-surface);
  line-height: 1.7;
  margin: 0;
}

.legal-page__text a {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}
</style>
