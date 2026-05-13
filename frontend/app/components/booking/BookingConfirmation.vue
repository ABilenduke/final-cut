<script setup lang="ts">
import QRCode from 'qrcode'
import type { Booking } from '~/types/booking'

const props = defineProps<{
  booking: Booking
}>()

const qrDataUrl = ref('')

onMounted(async () => {
  try {
    qrDataUrl.value = await QRCode.toDataURL(props.booking.confirmationCode, {
      width: 200,
      margin: 2,
      color: {
        dark: '#E5E2E1',
        light: '#131313',
      },
    })
  } catch {
    // QR generation failed — non-critical
  }
})

/** Escape text per RFC 5545 — backslashes, semicolons, commas, newlines */
function escapeIcsText(text: string): string {
  return text
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\n/g, '\\n')
}

function generateIcs() {
  const start = new Date(props.booking.startTime)
  const pad = (n: number) => n.toString().padStart(2, '0')
  const formatIcsDate = (d: Date) =>
    `${d.getUTCFullYear()}${pad(d.getUTCMonth() + 1)}${pad(d.getUTCDate())}T${pad(d.getUTCHours())}${pad(d.getUTCMinutes())}00Z`

  const end = new Date(start.getTime() + 2.5 * 60 * 60 * 1000) // Approximate 2.5 hour duration

  const seatList = props.booking.seats.map(s => s.label).join(', ')

  const now = new Date()
  const uid = `${props.booking.confirmationCode}@finalcut.test`

  const ics = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Final Cut//Booking//EN',
    'BEGIN:VEVENT',
    `UID:${uid}`,
    `DTSTAMP:${formatIcsDate(now)}`,
    `DTSTART:${formatIcsDate(start)}`,
    `DTEND:${formatIcsDate(end)}`,
    `SUMMARY:${escapeIcsText(props.booking.movieTitle + ' at Final Cut')}`,
    `DESCRIPTION:${escapeIcsText(`Screen: ${props.booking.screenName}\nSeats: ${seatList}\nBooking: ${props.booking.confirmationCode}`)}`,
    'END:VEVENT',
    'END:VCALENDAR',
  ].join('\r\n')

  const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `final-cut-${props.booking.confirmationCode}.ics`
  a.click()
  // Delay revocation to ensure download completes in all browsers
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

function handlePrint() {
  window.print()
}
</script>

<template>
  <div class="confirmation">
    <!-- Success banner -->
    <div class="confirmation__banner">
      <CvIcon name="check" size="lg" />
      <h2 class="confirmation__title">Booking Confirmed</h2>
    </div>

    <!-- Confirmation code -->
    <div class="confirmation__code-block">
      <span class="confirmation__code-label">Confirmation Code</span>
      <span class="confirmation__code">{{ booking.confirmationCode }}</span>
    </div>

    <!-- Movie details -->
    <div class="confirmation__details">
      <div v-if="booking.moviePosterUrl" class="confirmation__poster">
        <img
          :src="booking.moviePosterUrl"
          :alt="`${booking.movieTitle} poster`"
          class="confirmation__poster-img"
        >
      </div>
      <div class="confirmation__info">
        <h3 class="confirmation__movie-title">{{ booking.movieTitle }}</h3>
        <dl class="confirmation__meta">
          <div class="confirmation__meta-item">
            <dt>Date & Time</dt>
            <dd><time :datetime="booking.startTime">{{ formatDateTime(booking.startTime) }}</time></dd>
          </div>
          <div class="confirmation__meta-item">
            <dt>Screen</dt>
            <dd>{{ booking.screenName }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Seats -->
    <div class="confirmation__section">
      <h4 class="confirmation__section-title">Seats</h4>
      <ul class="confirmation__seat-list">
        <li
          v-for="seat in booking.seats"
          :key="seat.id"
          class="confirmation__seat-item"
        >
          <span>{{ seat.label }} ({{ seat.section }})</span>
          <span>{{ formatCurrency(seat.price) }}</span>
        </li>
      </ul>
    </div>

    <!-- Food items -->
    <div v-if="booking.foodItems.length > 0" class="confirmation__section">
      <h4 class="confirmation__section-title">Food & Drink</h4>
      <ul class="confirmation__seat-list">
        <li
          v-for="item in booking.foodItems"
          :key="item.itemId"
          class="confirmation__seat-item"
        >
          <span>{{ item.name }} x{{ item.quantity }}</span>
          <span>{{ formatCurrency(item.totalPrice) }}</span>
        </li>
      </ul>
    </div>

    <!-- Payment summary -->
    <div class="confirmation__payment">
      <div class="confirmation__payment-row">
        <span>Subtotal</span>
        <span>{{ formatCurrency(booking.subtotal) }}</span>
      </div>
      <div v-if="booking.discount > 0" class="confirmation__payment-row confirmation__payment-row--discount">
        <span>Discount</span>
        <span>-{{ formatCurrency(booking.discount) }}</span>
      </div>
      <div class="confirmation__payment-row confirmation__payment-row--total">
        <span>Total Paid</span>
        <span>{{ formatCurrency(booking.total) }}</span>
      </div>
    </div>

    <!-- QR Code -->
    <div v-if="qrDataUrl" class="confirmation__qr">
      <img
        :src="qrDataUrl"
        :alt="`Booking QR code for ${booking.confirmationCode}`"
        class="confirmation__qr-img"
      >
    </div>

    <!-- Actions -->
    <div class="confirmation__actions">
      <CvButton variant="secondary" @click="generateIcs">
        <template #icon-left>
          <CvIcon name="calendar-add" size="sm" />
        </template>
        Add to Calendar
      </CvButton>
      <CvButton variant="secondary" @click="handlePrint">
        <template #icon-left>
          <CvIcon name="print" size="sm" />
        </template>
        Print Tickets
      </CvButton>
    </div>

    <!-- Navigation -->
    <div class="confirmation__nav">
      <NuxtLink
        v-if="booking.userId"
        to="/account/orders"
        class="confirmation__link"
      >
        View in Order History
      </NuxtLink>
      <NuxtLink to="/" class="confirmation__link">
        Back to Home
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.confirmation {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  max-width: 40rem;
  margin-inline: auto;
}

/* Banner */
.confirmation__banner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
  color: var(--secondary);
  padding: var(--space-lg) 0;
}

.confirmation__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-lg);
  color: var(--on-surface);
}

/* Code block */
.confirmation__code-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-xs);
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border-radius: var(--radius-sm);
}

.confirmation__code-label {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
}

.confirmation__code {
  font-family: var(--font-display);
  font-size: var(--type-display-sm);
  letter-spacing: 0.05em;
  color: var(--secondary);
}

/* Movie details */
.confirmation__details {
  display: flex;
  gap: var(--space-md);
}

.confirmation__poster {
  flex-shrink: 0;
  width: 5rem;
}

.confirmation__poster-img {
  width: 100%;
  aspect-ratio: 2/3;
  object-fit: cover;
  object-position: top center;
  border-radius: var(--radius-sm);
}

.confirmation__info {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.confirmation__movie-title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  color: var(--on-surface);
}

.confirmation__meta {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.confirmation__meta-item {
  display: flex;
  gap: var(--space-sm);
}

.confirmation__meta-item dt {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
  min-width: 6rem;
}

.confirmation__meta-item dd {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--on-surface);
  margin: 0;
}

/* Sections */
.confirmation__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.confirmation__section-title {
  font-family: var(--font-display);
  font-size: var(--type-title-md);
  color: var(--on-surface);
}

.confirmation__seat-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.confirmation__seat-item {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
}

/* Payment */
.confirmation__payment {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
  padding-top: var(--space-md);
  border-top: var(--border-hairline) solid var(--outline-variant);
}

.confirmation__payment-row {
  display: flex;
  justify-content: space-between;
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
}

.confirmation__payment-row--discount {
  color: var(--secondary);
}

.confirmation__payment-row--total {
  font-family: var(--font-display);
  font-size: var(--type-title-md);
  color: var(--on-surface);
  padding-top: var(--space-sm);
}

.confirmation__payment-row--total span:last-child {
  color: var(--secondary);
}

/* QR Code */
.confirmation__qr {
  display: flex;
  justify-content: center;
  padding: var(--space-md) 0;
}

.confirmation__qr-img {
  width: 12.5rem;
  height: 12.5rem;
}

/* Actions */
.confirmation__actions {
  display: flex;
  gap: var(--space-md);
  justify-content: center;
  flex-wrap: wrap;
}

/* Navigation links */
.confirmation__nav {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
  padding-top: var(--space-md);
}

.confirmation__link {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--secondary);
  text-decoration: none;
}

.confirmation__link:hover {
  text-decoration: underline;
}
</style>
