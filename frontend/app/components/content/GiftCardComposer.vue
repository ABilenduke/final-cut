<script setup lang="ts">
import {
  EDITIONS,
  MAX_AMOUNT_CENTS,
  MAX_MESSAGE_LENGTH,
  MIN_AMOUNT_CENTS,
  PRESET_AMOUNTS_CENTS,
  editionMeta,
  nextSaturday,
  type ScheduleSlug,
} from '~/composables/useGiftCardComposer'
import type { GiftCardDeliveryMethod, GiftCardEdition } from '~/types/gift-card'

const composer = useGiftCardComposer()
const { state, errors } = composer

interface Preset {
  cents: number
  num: string
  sub: string
}

const PRESETS: Preset[] = [
  { cents: 2500, num: 'I', sub: 'One feature' },
  { cents: 5000, num: 'II', sub: 'Two seats' },
  { cents: 7500, num: 'III', sub: 'Date night' },
  { cents: 10000, num: 'IV', sub: "+ Director's flight" },
  { cents: 20000, num: 'V', sub: 'A season' },
]

const editionLabel = computed(() => editionMeta(state.value.edition).label)
const editionSerial = computed(() => editionMeta(state.value.edition).serial)

const customRaw = ref('')

const isPreset = computed(() =>
  PRESET_AMOUNTS_CENTS.includes(state.value.amountCents as (typeof PRESET_AMOUNTS_CENTS)[number]),
)

watch(
  () => state.value.amountCents,
  (cents) => {
    if (PRESET_AMOUNTS_CENTS.includes(cents as (typeof PRESET_AMOUNTS_CENTS)[number])) {
      customRaw.value = ''
    }
  },
  { immediate: true },
)

function selectPreset(cents: number): void {
  composer.setAmountCents(cents)
  customRaw.value = ''
  delete errors.value.amount
}

function onCustomInput(event: Event): void {
  const raw = (event.target as HTMLInputElement).value.replace(/[^0-9.]/g, '')
  customRaw.value = raw
  const dollars = Number(raw)
  if (!Number.isFinite(dollars) || raw === '') {
    composer.setAmountCents(0)
    return
  }
  const clamped = Math.min(MAX_AMOUNT_CENTS / 100, Math.max(0, dollars))
  composer.setAmountCents(Math.round(clamped * 100))
  delete errors.value.amount
}

function selectEdition(edition: GiftCardEdition): void {
  composer.setEdition(edition)
}

function selectDelivery(method: GiftCardDeliveryMethod): void {
  composer.setDeliveryMethod(method)
}

const scheduleChips = computed(() => {
  const sat = nextSaturday()
  return [
    { slug: 'now' as ScheduleSlug, primary: 'Now', secondary: 'Instant' },
    { slug: 'tomorrow' as ScheduleSlug, primary: 'Tomorrow', secondary: '9:00 AM' },
    {
      slug: 'weekend' as ScheduleSlug,
      primary: sat.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' }),
      secondary: 'Weekend',
    },
    { slug: 'custom' as ScheduleSlug, primary: 'Pick…', secondary: 'Custom' },
  ]
})

const customDateValue = ref<string>('')

function selectSchedule(slug: ScheduleSlug): void {
  if (slug === 'custom') {
    composer.setSchedule('custom', customDateValue.value || null)
  } else {
    composer.setSchedule(slug)
    customDateValue.value = ''
  }
}

function onCustomDateInput(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  customDateValue.value = value
  if (state.value.schedule === 'custom') {
    composer.setSchedule('custom', value ? new Date(value).toISOString() : null)
  }
}

const messageCount = computed(() => state.value.message.length)
const customAmountMin = computed(() => MIN_AMOUNT_CENTS / 100)
const customAmountMax = computed(() => MAX_AMOUNT_CENTS / 100)
</script>

<template>
  <div class="composer">
    <!-- §01 Denomination -->
    <section class="composer__block">
      <header class="composer__head">
        <span class="composer__label">
          <span class="composer__num">01</span> Choose a denomination
        </span>
        <span class="composer__hint">USD · Tax-exempt</span>
      </header>

      <div class="composer__amounts" role="radiogroup" aria-label="Gift card amount">
        <button
          v-for="preset in PRESETS"
          :key="preset.cents"
          type="button"
          class="composer__amount"
          :class="{ 'composer__amount--active': isPreset && state.amountCents === preset.cents }"
          :data-num="preset.num"
          :aria-pressed="isPreset && state.amountCents === preset.cents"
          @click="selectPreset(preset.cents)"
        >
          <span class="composer__amount-value">
            <span class="composer__amount-cur">$</span>{{ preset.cents / 100 }}
          </span>
          <span class="composer__amount-sub">{{ preset.sub }}</span>
        </button>
      </div>

      <div class="composer__custom" :class="{ 'composer__custom--focus': !isPreset && customRaw !== '' }">
        <span class="composer__custom-label">or</span>
        <div class="composer__custom-input-wrap">
          <span class="composer__custom-cur">$</span>
          <input
            type="text"
            inputmode="decimal"
            class="composer__custom-input"
            placeholder="Enter custom amount"
            aria-label="Custom amount"
            :value="customRaw"
            @input="onCustomInput"
          >
        </div>
        <span class="composer__custom-scope">
          <b>${{ customAmountMin }} min · ${{ customAmountMax }} max</b>
          Increments of $1
        </span>
      </div>

      <p v-if="errors.amount" class="composer__error" role="alert">{{ errors.amount }}</p>
    </section>

    <!-- §02 Card design -->
    <section class="composer__block">
      <header class="composer__head">
        <span class="composer__label">
          <span class="composer__num">02</span> Card design
        </span>
        <span class="composer__hint">Three editions · No charge</span>
      </header>

      <div class="composer__edition-meta">
        <span class="composer__edition-caption">
          <b>{{ editionLabel }}</b> · No. <span>{{ editionSerial }}</span> of 250 · seasonal
        </span>
        <div class="composer__swatches" role="radiogroup" aria-label="Card edition">
          <button
            v-for="edition in EDITIONS"
            :key="edition.value"
            type="button"
            class="composer__swatch"
            :class="[
              `composer__swatch--${edition.value}`,
              { 'composer__swatch--active': state.edition === edition.value },
            ]"
            :aria-label="`${edition.label} edition`"
            :aria-pressed="state.edition === edition.value"
            @click="selectEdition(edition.value)"
          />
        </div>
      </div>
    </section>

    <!-- §03 Delivery -->
    <section class="composer__block">
      <header class="composer__head">
        <span class="composer__label">
          <span class="composer__num">03</span> How shall we deliver it?
        </span>
        <span class="composer__hint">Free for both</span>
      </header>

      <div class="composer__delivery" role="radiogroup" aria-label="Delivery method">
        <button
          type="button"
          class="composer__delivery-opt"
          :class="{ 'composer__delivery-opt--active': state.deliveryMethod === 'email' }"
          :aria-pressed="state.deliveryMethod === 'email'"
          @click="selectDelivery('email')"
        >
          <span class="composer__delivery-head">
            <span>By email</span>
            <CvIcon name="mail" size="md" />
          </span>
          <span class="composer__delivery-sub">
            Arrives instantly. Recipient gets a redeemable code and the card art.
          </span>
        </button>
        <button
          type="button"
          class="composer__delivery-opt"
          :class="{ 'composer__delivery-opt--active': state.deliveryMethod === 'print' }"
          :aria-pressed="state.deliveryMethod === 'print'"
          @click="selectDelivery('print')"
        >
          <span class="composer__delivery-head">
            <span>Printed &amp; posted</span>
            <CvIcon name="print" size="md" />
          </span>
          <span class="composer__delivery-sub">
            Heavy stock, black sleeve, hand-stamped. 3–5 business days. + $0.
          </span>
        </button>
      </div>
    </section>

    <!-- §04 Recipient -->
    <section class="composer__block">
      <header class="composer__head">
        <span class="composer__label">
          <span class="composer__num">04</span> The recipient
        </span>
        <span class="composer__hint">All fields required</span>
      </header>

      <div class="composer__row-2">
        <div class="composer__field">
          <label for="gc-recipient-name">Recipient name <span class="composer__field-req">*</span></label>
          <input
            id="gc-recipient-name"
            class="composer__field-input"
            placeholder="Margot Renard"
            :value="state.recipientName"
            :aria-invalid="!!errors.recipientName"
            @input="composer.setRecipientName(($event.target as HTMLInputElement).value)"
          >
          <span v-if="errors.recipientName" class="composer__field-error">{{ errors.recipientName }}</span>
        </div>
        <div class="composer__field">
          <label for="gc-recipient-email">Recipient email <span class="composer__field-req">*</span></label>
          <input
            id="gc-recipient-email"
            class="composer__field-input"
            type="email"
            placeholder="margot@example.com"
            :value="state.recipientEmail"
            :aria-invalid="!!errors.recipientEmail"
            @input="composer.setRecipientEmail(($event.target as HTMLInputElement).value)"
          >
          <span v-if="errors.recipientEmail" class="composer__field-error">{{ errors.recipientEmail }}</span>
        </div>
      </div>

      <div class="composer__row-2">
        <div class="composer__field">
          <label for="gc-sender-name">Your name <span class="composer__field-req">*</span></label>
          <input
            id="gc-sender-name"
            class="composer__field-input"
            placeholder="From"
            :value="state.senderName"
            :aria-invalid="!!errors.senderName"
            @input="composer.setSenderName(($event.target as HTMLInputElement).value)"
          >
          <span v-if="errors.senderName" class="composer__field-error">{{ errors.senderName }}</span>
        </div>
        <div class="composer__field">
          <label>Send on <span class="composer__field-hint">Pick a date</span></label>
          <div class="composer__schedule" role="radiogroup" aria-label="Delivery schedule">
            <button
              v-for="chip in scheduleChips"
              :key="chip.slug"
              type="button"
              class="composer__chip"
              :class="{ 'composer__chip--active': state.schedule === chip.slug }"
              :aria-pressed="state.schedule === chip.slug"
              @click="selectSchedule(chip.slug)"
            >
              <span class="composer__chip-d">{{ chip.primary }}</span>
              <span class="composer__chip-l">{{ chip.secondary }}</span>
            </button>
          </div>
          <input
            v-if="state.schedule === 'custom'"
            type="date"
            class="composer__field-input composer__custom-date"
            :value="customDateValue"
            :aria-invalid="!!errors.scheduleDateIso"
            @input="onCustomDateInput"
          >
          <span v-if="errors.scheduleDateIso" class="composer__field-error">{{ errors.scheduleDateIso }}</span>
        </div>
      </div>
    </section>

    <!-- §05 Message -->
    <section class="composer__block">
      <header class="composer__head">
        <span class="composer__label">
          <span class="composer__num">05</span> A note, if you'd like
        </span>
        <span class="composer__hint">{{ messageCount }} / {{ MAX_MESSAGE_LENGTH }}</span>
      </header>
      <div class="composer__field">
        <textarea
          class="composer__field-textarea"
          :maxlength="MAX_MESSAGE_LENGTH"
          placeholder="Something to read before the lights go down…"
          :value="state.message"
          @input="composer.setMessage(($event.target as HTMLTextAreaElement).value)"
        />
      </div>
    </section>
  </div>
</template>

<style scoped>
.composer {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.composer__block {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.composer__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-md);
  padding-bottom: 0.5rem;
  border-bottom: 1px solid rgba(87, 66, 62, 0.2);
}

.composer__label {
  font-family: var(--font-display);
  font-size: 1.5rem;
  letter-spacing: -0.015em;
  line-height: 1;
  color: var(--on-surface);
  font-weight: 500;
  display: flex;
  align-items: baseline;
  gap: 0.625rem;
}

.composer__num {
  font-family: var(--font-display);
  font-size: 0.6875rem;
  color: var(--secondary);
  letter-spacing: 0.22em;
  text-transform: uppercase;
  font-weight: 500;
}

.composer__hint {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

/* Amount picker */
.composer__amounts {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.5rem;
}

.composer__amount {
  position: relative;
  background: var(--surface-container-low);
  border: 1px solid rgba(87, 66, 62, 0.25);
  border-radius: var(--radius-sm);
  padding: 1.125rem 0.5rem 0.75rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  cursor: pointer;
  transition: all 200ms var(--ease-standard);
  text-align: center;
  color: var(--on-surface);
  font-family: var(--font-body);
}

.composer__amount::before {
  content: attr(data-num);
  position: absolute;
  top: 0.5rem;
  left: 0.625rem;
  font-family: var(--font-display);
  font-size: 0.5625rem;
  letter-spacing: 0.22em;
  color: var(--on-tertiary-fixed-variant);
}

.composer__amount-value {
  font-family: var(--font-display);
  font-size: 1.625rem;
  letter-spacing: -0.02em;
  line-height: 1;
  font-weight: 500;
}

.composer__amount-cur {
  font-size: 0.65em;
  color: var(--tertiary);
  vertical-align: 0.25em;
  margin-right: 0.05em;
}

.composer__amount-sub {
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-top: 0.25rem;
}

.composer__amount:hover {
  border-color: var(--outline);
  background: var(--surface-container);
}

.composer__amount--active {
  background: var(--primary-container);
  color: var(--primary);
  border-color: var(--primary-container);
}

.composer__amount--active::before,
.composer__amount--active .composer__amount-cur,
.composer__amount--active .composer__amount-sub {
  color: rgba(255, 180, 168, 0.8);
}

.composer__custom {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-md);
  align-items: center;
  padding: var(--space-md) var(--space-lg);
  background: var(--surface-container-low);
  border: 1px solid rgba(87, 66, 62, 0.25);
  border-radius: var(--radius-sm);
  transition: border-color 200ms;
}

.composer__custom:focus-within,
.composer__custom--focus {
  border-color: var(--secondary);
}

.composer__custom-label {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  font-weight: 500;
}

.composer__custom-input-wrap {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
  font-family: var(--font-display);
}

.composer__custom-cur {
  color: var(--tertiary);
  font-size: 1.125rem;
  font-weight: 500;
}

.composer__custom-input {
  font-family: var(--font-display);
  font-size: 1.625rem;
  letter-spacing: -0.02em;
  width: 100%;
  color: var(--on-surface);
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  background: transparent;
  border: none;
  outline: none;
}

.composer__custom-input::placeholder {
  color: var(--on-tertiary-fixed-variant);
}

.composer__custom-scope {
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: right;
  line-height: 1.4;
}

.composer__custom-scope b {
  color: var(--tertiary);
  font-weight: 500;
  display: block;
}

/* Edition swatches */
.composer__edition-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-md) 0;
  border-top: none;
  border-bottom: 1px solid rgba(87, 66, 62, 0.2);
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.composer__edition-caption b {
  color: var(--on-surface);
  font-family: var(--font-display);
  font-weight: 500;
  letter-spacing: 0.04em;
  text-transform: none;
  font-size: 0.875rem;
}

.composer__swatches {
  display: flex;
  gap: 0.4rem;
}

.composer__swatch {
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 50%;
  cursor: pointer;
  border: 1px solid rgba(218, 199, 105, 0.2);
  background: transparent;
  padding: 0;
  transition: transform 150ms, border-color 150ms;
}

.composer__swatch:hover {
  transform: scale(1.1);
}

.composer__swatch--active {
  border-color: var(--secondary);
  box-shadow: 0 0 0 2px var(--surface), 0 0 0 3px var(--secondary);
}

.composer__swatch--reactor {
  background: linear-gradient(145deg, #1a0a0a, #550000);
}

.composer__swatch--gold {
  background: linear-gradient(145deg, #3a3318, #dac769);
}

.composer__swatch--void {
  background: linear-gradient(145deg, #1c1c1c, #000);
}

/* Delivery toggle */
.composer__delivery {
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: var(--surface-container-low);
  border: 1px solid rgba(87, 66, 62, 0.25);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.composer__delivery-opt {
  padding: var(--space-md) var(--space-lg);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  background: transparent;
  border: none;
  border-right: 1px solid rgba(87, 66, 62, 0.2);
  text-align: left;
  font-family: var(--font-body);
  color: var(--on-surface);
  transition: background 200ms, color 200ms;
}

.composer__delivery-opt:last-child {
  border-right: none;
}

.composer__delivery-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 500;
  color: var(--on-surface);
}

.composer__delivery-head :deep(svg) {
  width: 18px;
  height: 18px;
  color: var(--on-tertiary-fixed-variant);
  transition: color 200ms;
}

.composer__delivery-sub {
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  line-height: 1.4;
}

.composer__delivery-opt--active {
  background: var(--surface-container-high);
}

.composer__delivery-opt--active .composer__delivery-head,
.composer__delivery-opt--active .composer__delivery-head :deep(svg) {
  color: var(--secondary);
}

/* Field rows */
.composer__row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-md);
}

.composer__row-2 > * {
  min-width: 0;
}

.composer__field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.composer__field label {
  font-size: 0.6875rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.composer__field-req {
  color: var(--secondary);
}

.composer__field-hint {
  color: var(--on-tertiary-fixed-variant);
  font-size: 0.625rem;
}

.composer__field-input,
.composer__field-textarea {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  letter-spacing: -0.005em;
  color: var(--on-surface);
  border: none;
  border-bottom: 1px solid var(--outline);
  padding: 0.625rem 0;
  transition: border-color 200ms;
  background: transparent;
  outline: none;
}

.composer__field-input::placeholder,
.composer__field-textarea::placeholder {
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  font-family: var(--font-body);
  font-size: 1rem;
}

.composer__field-input:focus,
.composer__field-textarea:focus {
  border-bottom-color: var(--secondary);
}

.composer__field-textarea {
  resize: none;
  font-family: var(--font-body);
  font-size: 1.0625rem;
  line-height: 1.55;
  font-style: italic;
  min-height: 5.5rem;
  width: 100%;
}

.composer__field-error {
  font-size: 0.75rem;
  color: var(--state-danger-text, var(--primary));
  letter-spacing: 0.04em;
  text-transform: none;
}

/* Schedule chips */
.composer__schedule {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.4rem;
  margin-top: 0.5rem;
}

.composer__chip {
  padding: 0.625rem 0.5rem;
  background: var(--surface-container-low);
  border: 1px solid rgba(87, 66, 62, 0.25);
  border-radius: var(--radius-sm);
  text-align: center;
  cursor: pointer;
  transition: all 150ms;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  font-family: var(--font-body);
  color: var(--on-surface);
}

.composer__chip-d {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  letter-spacing: -0.01em;
}

.composer__chip-l {
  font-size: 0.5625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.composer__chip:hover {
  border-color: var(--outline);
}

.composer__chip--active {
  background: var(--primary-container);
  border-color: var(--primary-container);
  color: var(--primary);
}

.composer__chip--active .composer__chip-l {
  color: rgba(255, 180, 168, 0.8);
}

.composer__custom-date {
  margin-top: 0.5rem;
}

.composer__error {
  font-size: 0.8125rem;
  color: var(--state-danger-text, var(--primary));
  margin: 0;
}

/* Responsive */
@media (max-width: 40rem) {
  .composer__amounts {
    grid-template-columns: repeat(2, 1fr);
  }
  .composer__delivery {
    grid-template-columns: 1fr;
  }
  .composer__delivery-opt {
    border-right: none;
    border-bottom: 1px solid rgba(87, 66, 62, 0.2);
  }
  .composer__delivery-opt:last-child {
    border-bottom: none;
  }
  .composer__schedule {
    grid-template-columns: repeat(2, 1fr);
  }
  .composer__row-2 {
    grid-template-columns: 1fr;
  }
}
</style>
