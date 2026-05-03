<script setup lang="ts">
useHead({
  title: 'Privacy — Final Cut',
  meta: [
    { name: 'description', content: 'How Final Cut handles account, booking, payment, location, and communication information.' },
  ],
})
</script>

<template>
  <main class="legal-page">
    <div class="close-up">
      <p class="legal-page__eyebrow">Final Cut</p>
      <h1 class="legal-page__title display-sm">Privacy</h1>
      <p class="legal-page__updated body-sm">Last updated May 3, 2026</p>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Information We Collect</h2>
        <p class="legal-page__text body-md">
          We collect information you provide when creating an account, booking tickets, ordering concessions, joining loyalty features, purchasing gift cards, or contacting us.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">How We Use It</h2>
        <p class="legal-page__text body-md">
          We use this information to process reservations, manage orders, support your account, improve cinema operations, prevent fraud, and send service messages related to your booking.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Payments</h2>
        <p class="legal-page__text body-md">
          Payment details are handled by our payment processor. Final Cut does not store full card numbers in the application.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Location Features</h2>
        <p class="legal-page__text body-md">
          If you allow browser location access, we use it only to estimate nearby cinemas and show distance labels. You can revoke this permission in your browser settings.
        </p>
      </section>

      <section class="legal-page__section">
        <h2 class="legal-page__heading headline-sm">Contact</h2>
        <p class="legal-page__text body-md">
          Privacy questions can be sent to <a href="mailto:privacy@finalcut.test">privacy@finalcut.test</a>.
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
