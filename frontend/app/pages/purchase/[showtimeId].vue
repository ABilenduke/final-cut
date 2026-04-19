<script setup lang="ts">
import type { Showtime } from '~/types/showtime'
import type { Auditorium, Seat } from '~/types/auditorium'
import type { BookingSeat } from '~/types/booking'
import { apiFetch } from '~/utils/api'

type Preference = 'together' | 'aisle' | 'center' | 'wheelchair'

definePageMeta({
  layout: false,
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

setStep(1, [], [])

const showtime = ref<Showtime | null>(null)
const auditorium = ref<Auditorium | null>(null)
const allSeats = ref<Seat[]>([])
const loading = ref(true)
const error = ref('')

const partySize = ref(2)
const preferences = ref<Preference[]>([])

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

    // Seed party size from any existing cart seats (return-from-checkout flow)
    if (cart.seats.value.length > partySize.value) {
      partySize.value = cart.seats.value.length
    }
  } catch {
    error.value = 'Unable to load showtime. Please try again.'
    showToast({ message: 'Unable to load showtime.', type: 'error' })
  } finally {
    loading.value = false
  }
})

const selectedSeatIds = computed(() => cart.seats.value.map(s => s.seatId))

function sectionLabel(seat: Seat): string {
  return seat.type.charAt(0).toUpperCase() + seat.type.slice(1)
}

function toBookingSeat(seat: Seat): BookingSeat {
  return {
    seatId: seat.id,
    section: sectionLabel(seat),
    price: seat.price,
  }
}

function handleSeatToggle(payload: { seatId: string; selected: boolean }) {
  const seat = allSeats.value.find(s => s.id === payload.seatId)
  if (!seat) return

  if (payload.selected) {
    // Enforce party size — if at cap, drop the oldest selection first
    if (cart.seats.value.length >= partySize.value) {
      const first = cart.seats.value[0]
      if (first) cart.removeSeat(first.seatId)
    }
    cart.addSeat(toBookingSeat(seat))
  } else {
    cart.removeSeat(payload.seatId)
  }
}

function handlePartyChange(next: number) {
  partySize.value = next
  while (cart.seats.value.length > partySize.value) {
    const last = cart.seats.value[cart.seats.value.length - 1]
    if (!last) break
    cart.removeSeat(last.seatId)
  }
}

function clearSelections() {
  for (const seat of [...cart.seats.value]) {
    cart.removeSeat(seat.seatId)
  }
}

// Sorted row letters (A, B, C, ...)
const sortedRows = computed<string[]>(() => {
  const rows = new Set<string>()
  for (const seat of allSeats.value) rows.add(seat.row)
  return [...rows].sort()
})

// Row preference for auto-pick: start from the center and alternate outward
const rowPreferenceOrder = computed<string[]>(() => {
  const rows = sortedRows.value
  const n = rows.length
  if (n === 0) return []
  const center = Math.floor((n - 1) / 2)
  const ordered: string[] = []
  for (let offset = 0; offset <= n; offset++) {
    const forward = center + offset
    const back = center - offset
    if (offset === 0) {
      ordered.push(rows[center])
    } else {
      if (forward < n) ordered.push(rows[forward])
      if (back >= 0) ordered.push(rows[back])
    }
  }
  return ordered.filter((r, i, arr) => arr.indexOf(r) === i)
})

function seatsOfRow(row: string): Seat[] {
  return allSeats.value
    .filter(s => s.row === row)
    .sort((a, b) => a.number - b.number)
}

function findContiguousRun(row: string, size: number): Seat[] | null {
  const seats = seatsOfRow(row)
  if (seats.length < size) return null
  for (let i = 0; i <= seats.length - size; i++) {
    const window = seats.slice(i, i + size)
    const ok = window.every((s, idx) => {
      if (s.status !== 'available') return false
      // Accessible seats are reserved — skip them in auto-pick
      if (s.type === 'accessible') return false
      // Ensure they're truly contiguous (no seat-number gap, though seats are dense)
      if (idx === 0) return true
      return s.number === window[idx - 1].number + 1
    })
    if (ok) return window
  }
  return null
}

function handleAutoPick() {
  if (!auditorium.value) return
  clearSelections()
  for (const row of rowPreferenceOrder.value) {
    const run = findContiguousRun(row, partySize.value)
    if (run) {
      for (const seat of run) cart.addSeat(toBookingSeat(seat))
      return
    }
  }
  showToast({
    message: `No contiguous run of ${partySize.value} seats found. Try a smaller party or pick individually.`,
    type: 'info',
  })
}

const projectionistPick = computed<string[]>(() => {
  if (!allSeats.value.length) return []
  for (const row of rowPreferenceOrder.value) {
    const pair = findContiguousRun(row, 2)
    if (pair) return pair.map(s => s.id)
  }
  return []
})

function handleProjectionistPick() {
  const ids = projectionistPick.value
  if (ids.length < 2) return
  if (partySize.value < 2) partySize.value = 2
  clearSelections()
  for (const id of ids) {
    const seat = allSeats.value.find(s => s.id === id)
    if (seat) cart.addSeat(toBookingSeat(seat))
  }
}

// Legend "from" prices — min per tier in cents
const priceByTier = computed<{ standard?: number; premium?: number; accessible?: number }>(() => {
  const out: { standard?: number; premium?: number; accessible?: number } = {}
  for (const seat of allSeats.value) {
    const key = seat.type
    if (out[key] === undefined || (seat.price ?? 0) < (out[key] ?? Infinity)) {
      out[key] = seat.price
    }
  }
  return out
})

const canProceed = computed(() => cart.seats.value.length > 0)

function handleContinue() {
  if (canProceed.value) navigateTo('/purchase/snacks')
}

function handleRemoveSeat(seatId: string) {
  cart.removeSeat(seatId)
}

function handleRelease() {
  if (!cart.showtime.value) {
    cart.clear()
    navigateTo('/')
    return
  }
  clearSelections()
  showToast({ message: 'Seats released.', type: 'info' })
}

const changeSeatsHref = computed<string | null>(() =>
  cart.showtime.value ? `/purchase/${cart.showtime.value.id}` : null,
)
</script>

<template>
  <NuxtLayout name="purchase">
    <!-- Hold strip appears below the fixed header once a seat is selected -->
    <template #below-header>
      <CheckoutHoldTimer
        v-if="cart.showtime.value && cart.seats.value.length > 0"
        :time-remaining="cart.timeRemaining.value"
        :auditorium="cart.showtime.value.screenName"
        :seats="cart.seats.value"
        :change-seats-href="changeSeatsHref"
        @release="handleRelease"
      />
    </template>

    <template #header-extras>
      <div v-if="activeLocation" class="seat-loc">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" /></svg>
        <b>{{ activeLocation.name }}</b>
      </div>
    </template>

    <template #rail>
      <SeatSelectionRail
        :showtime="showtime"
        :seats="cart.seats.value"
        :party-size="partySize"
        :time-remaining="cart.timeRemaining.value"
        @remove-seat="handleRemoveSeat"
        @continue="handleContinue"
      />
      <SeatProjectionistPick
        :pick-seats="projectionistPick"
        @take-pick="handleProjectionistPick"
      />
    </template>

    <div class="seat-page" :class="{ 'seat-page--with-hold': cart.seats.value.length > 0 }">
      <template v-if="loading">
        <CvSkeletonLoader variant="card" height="22rem" />
      </template>

      <template v-else-if="error">
        <div class="seat-page__error">
          <p>{{ error }}</p>
          <CvButton href="/">Back to Home</CvButton>
        </div>
      </template>

      <template v-else-if="showtime && auditorium">
        <SeatSelectionHero :showtime="showtime" :auditorium="auditorium" />

        <SeatSelectionControls
          :party-size="partySize"
          :preferences="preferences"
          :max="10"
          @update:party-size="handlePartyChange"
          @update:preferences="(v) => preferences = v"
          @auto-pick="handleAutoPick"
        />

        <SeatAuditoriumStage
          :auditorium="auditorium"
          :seats="allSeats"
          :selected-seat-ids="selectedSeatIds"
          @seat-toggled="handleSeatToggle"
        />

        <SeatSelectionLegend
          :standard-from="priceByTier.standard"
          :premiere-from="priceByTier.premium"
          :accessible-from="priceByTier.accessible"
        />

        <SeatSightlineDiagram />

        <SeatSelectionHouseRules />

        <!-- Mobile-only inline rail (below 60rem the layout rail is hidden) -->
        <div class="seat-page__mobile-rail">
          <SeatSelectionRail
            :showtime="showtime"
            :seats="cart.seats.value"
            :party-size="partySize"
            :time-remaining="cart.timeRemaining.value"
            @remove-seat="handleRemoveSeat"
            @continue="handleContinue"
          />
          <SeatProjectionistPick
            :pick-seats="projectionistPick"
            @take-pick="handleProjectionistPick"
          />
        </div>
      </template>
    </div>
  </NuxtLayout>
</template>

<style scoped>
.seat-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
  padding-top: var(--space-lg);
}

.seat-page--with-hold {
  padding-top: 3rem; /* compensate for the 2.5rem hold strip + breathing room */
}

.seat-page__error {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-3xl) 0;
  color: var(--tertiary);
}

.seat-page__mobile-rail {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

@media (min-width: 60rem) {
  /* At ≥60rem the layout renders the rail via the #rail slot, so hide the inline mobile rail */
  .seat-page__mobile-rail {
    display: none;
  }
}

.seat-loc {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-family: var(--font-body);
  color: var(--tertiary);
  font-size: 0.8125rem;
  padding: 0.375rem 0.625rem;
  background-color: rgba(42, 42, 42, 0.5);
  border-radius: 0.125rem;
}

.seat-loc svg {
  width: 0.875rem;
  height: 0.875rem;
}

.seat-loc b {
  color: var(--on-surface);
  font-weight: 500;
}

@media (max-width: 40rem) {
  .seat-loc {
    display: none;
  }
}
</style>
