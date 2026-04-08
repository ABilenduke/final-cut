<script setup lang="ts">
import type { Showtime } from '~/types/showtime'
import type { Auditorium, Seat } from '~/types/auditorium'
import type { BookingSeat } from '~/types/booking'
import { apiFetch } from '~/utils/api'

definePageMeta({
  layout: 'purchase',
})

useHead({
  title: 'Pick Your Seats — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const route = useRoute()
const showtimeId = route.params.showtimeId as string

const { activeLocation } = useLocations()
const cart = useCart()
const { show: showToast } = useToast()
const { setStep } = usePurchaseStep()

// Set step indicator
setStep(1, [], [])

const showtime = ref<Showtime | null>(null)
const auditorium = ref<Auditorium | null>(null)
const allSeats = ref<Seat[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  if (!activeLocation.value) {
    showToast({ message: 'Please select a location first.', type: 'error' })
    await navigateTo('/')
    return
  }

  try {
    const response = await apiFetch<{
      data: { showtime: Showtime; auditorium: Auditorium; seats: Seat[] }
    }>(`/api/locations/${activeLocation.value.slug}/showtimes/${showtimeId}`)

    showtime.value = response.data.showtime
    auditorium.value = response.data.auditorium
    allSeats.value = response.data.seats

    cart.initializeCart(response.data.showtime)
  } catch {
    error.value = 'Unable to load showtime. Please try again.'
    showToast({ message: 'Unable to load showtime.', type: 'error' })
  } finally {
    loading.value = false
  }
})

const selectedSeatIds = computed(() => cart.seats.value.map(s => s.seatId))

function handleSeatToggle(payload: { seatId: string; selected: boolean }) {
  const seat = allSeats.value.find(s => s.id === payload.seatId)
  if (!seat) return

  if (payload.selected) {
    const bookingSeat: BookingSeat = {
      seatId: seat.id,
      section: seat.type.charAt(0).toUpperCase() + seat.type.slice(1),
      price: seat.price,
    }
    cart.addSeat(bookingSeat)
  } else {
    cart.removeSeat(payload.seatId)
  }
}

const canProceed = computed(() => cart.seats.value.length > 0)

function handleContinue() {
  if (canProceed.value) {
    navigateTo('/purchase/checkout')
  }
}
</script>

<template>
  <div class="seat-page">
    <template v-if="loading">
      <CvSkeletonLoader variant="card" height="20rem" />
    </template>

    <template v-else-if="error">
      <div class="seat-page__error">
        <p>{{ error }}</p>
        <CvButton href="/">Back to Home</CvButton>
      </div>
    </template>

    <template v-else-if="showtime && auditorium">
      <!-- Top bar -->
      <div class="seat-page__info">
        <h1 class="seat-page__title">{{ showtime.movieTitle }}</h1>
        <p class="seat-page__meta">
          <time :datetime="showtime.startTime">{{ formatDateTime(showtime.startTime) }}</time>
          · {{ showtime.screenName }}
        </p>
      </div>

      <!-- Seat grid -->
      <AuditoriumGrid
        :auditorium="auditorium"
        :seats="allSeats"
        :selected-seat-ids="selectedSeatIds"
        @seat-toggled="handleSeatToggle"
      />

      <!-- Legend -->
      <AuditoriumLegend />

      <!-- Continue CTA -->
      <div class="seat-page__actions">
        <CvButton
          variant="primary"
          size="lg"
          :disabled="!canProceed"
          class="seat-page__cta"
          @click="handleContinue"
        >
          Continue to Checkout
        </CvButton>
      </div>
    </template>
  </div>
</template>

<style scoped>
.seat-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.seat-page__info {
  text-align: center;
}

.seat-page__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-md);
  color: var(--on-surface);
}

.seat-page__meta {
  font-family: var(--font-body);
  font-size: var(--type-body-md);
  color: var(--tertiary);
  margin-top: var(--space-xs);
}

.seat-page__error {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-3xl) 0;
  color: var(--tertiary);
}

.seat-page__actions {
  display: flex;
  justify-content: center;
  padding: var(--space-md) 0;
}

.seat-page__cta {
  min-width: 16rem;
}

@media (max-width: 59.999rem) {
  .seat-page {
    padding-bottom: 4.5rem;
  }
}
</style>
