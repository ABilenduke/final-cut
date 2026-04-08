<script setup lang="ts">
definePageMeta({ layout: 'account', middleware: 'auth' })
useHead({
  title: 'Upcoming Bookings — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const { bookings } = useAccount()
const { data: bookingsData } = await bookings()
</script>

<template>
  <div class="bookings-page">
    <h1 class="bookings-page__title">Upcoming Bookings</h1>

    <div class="bookings-page__content">
      <UpcomingBookings :bookings="bookingsData?.data ?? []" />
    </div>
  </div>
</template>

<style scoped>
.bookings-page__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-lg);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-xl);
}

.bookings-page__content {
  max-width: 40rem;
  margin-inline: auto;
}
</style>
