<script setup lang="ts">
useHead({
  title: 'Visit Us — Final Cut',
  meta: [
    { name: 'description', content: 'Find directions, hours, and contact information for Final Cut theater.' },
  ],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'LocalBusiness',
        name: 'Final Cut',
        description: 'A cinematic movie theater experience.',
        address: {
          '@type': 'PostalAddress',
          streetAddress: '123 Cinema Boulevard',
          addressLocality: 'New York',
          addressRegion: 'NY',
          postalCode: '10001',
          addressCountry: 'US',
        },
        telephone: '+1-212-555-0199',
        email: 'hello@finalcut.test',
      }),
    },
  ],
})

const coordinates = { lat: 40.7128, lng: 73.9352 }
</script>

<template>
  <div class="contact-page">
    <div class="container">
      <h1 class="contact-page__title display-sm">Visit Us</h1>
      <div class="establishing-shot">
        <div class="establishing-shot__primary">
          <ContactMap
            :coordinates="coordinates"
            address="123 Cinema Boulevard, New York, NY 10001"
          />

          <section class="contact-page__info">
            <h2 class="headline-sm">Getting Here</h2>
            <div class="contact-page__directions">
              <div>
                <h3 class="title-lg">By Car</h3>
                <p class="body-md">Parking garage located directly beneath the theater. Enter via Cinema Lane. First 3 hours validated with ticket purchase.</p>
              </div>
              <div>
                <h3 class="title-lg">By Transit</h3>
                <p class="body-md">Nearest subway: Cinema Station (A/C/E lines), one block east. Bus routes 14, 23, and 42 stop at Cinema Boulevard.</p>
              </div>
            </div>
          </section>

          <section class="contact-page__accessibility-info">
            <h2 class="headline-sm">Accessibility</h2>
            <p class="body-md">Step-free access at all entrances. Accessible parking spaces available on Level 1 of the garage, nearest to the elevator. Wheelchair-accessible seating available in every auditorium.</p>
          </section>
        </div>

        <div class="establishing-shot__secondary">
          <section class="contact-page__hours">
            <h2 class="headline-sm">Hours</h2>
            <dl class="contact-page__hours-list">
              <div class="contact-page__hours-row">
                <dt class="body-md">Monday – Thursday</dt>
                <dd class="body-md">11:00 AM – 11:00 PM</dd>
              </div>
              <div class="contact-page__hours-row">
                <dt class="body-md">Friday – Saturday</dt>
                <dd class="body-md">10:00 AM – 1:00 AM</dd>
              </div>
              <div class="contact-page__hours-row">
                <dt class="body-md">Sunday</dt>
                <dd class="body-md">10:00 AM – 10:00 PM</dd>
              </div>
            </dl>
          </section>

          <section class="contact-page__details">
            <h2 class="headline-sm">Contact</h2>
            <p class="body-md"><strong>Phone:</strong> (212) 555-0199</p>
            <p class="body-md"><strong>Email:</strong> hello@finalcut.test</p>
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
