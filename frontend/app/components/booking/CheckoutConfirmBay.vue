<script setup lang="ts">
const props = defineProps<{
  /** Authoritative order total (cents). */
  total: number
  /** Seconds left on the seat hold — mirrored in the authorization note. */
  timeRemaining: number
  /** In-flight submit — shows a spinner label and disables the CTA. */
  submitting?: boolean
  /** Disables the CTA (e.g. Stripe not ready, or no seats) without a spinner. */
  disabled?: boolean
  /** Terms-consent checkbox state (v-model). */
  acceptTerms?: boolean
}>()

const emit = defineEmits<{
  submit: []
  'update:acceptTerms': [value: boolean]
}>()

const termsModel = computed<boolean>({
  get: () => props.acceptTerms ?? false,
  set: (v) => emit('update:acceptTerms', v),
})

const formattedHold = computed<string>(() => {
  const mins = Math.floor(Math.max(0, props.timeRemaining) / 60)
  const secs = Math.max(0, props.timeRemaining) % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})
</script>

<template>
  <section class="bay confirm-bay">
    <header class="bay__header confirm-bay__header">
      <div>
        <div class="bay__number">§ 05</div>
        <h2 class="bay__title">Confirm &amp; <em>pay.</em></h2>
      </div>
    </header>

    <label class="confirm-bay__check">
      <input v-model="termsModel" type="checkbox" class="confirm-bay__check-box">
      <span class="confirm-bay__check-text">
        I agree to the <a href="#" class="confirm-bay__link">ticketing terms</a>
        and the <a href="#" class="confirm-bay__link">auditorium policy</a>.
        No late entry after 10 minutes; phones silenced and stowed.
      </span>
    </label>

    <button
      type="button"
      class="confirm-bay__pay"
      :disabled="disabled || submitting"
      @click="emit('submit')"
    >
      <span>{{ submitting ? 'Processing…' : 'Confirm & pay' }}</span>
      <span class="confirm-bay__pay-amt">{{ formatCurrency(total) }}</span>
    </button>

    <p class="confirm-bay__note">
      By paying, you authorize Final Cut Ltd. to charge your card.
      Seats release if payment does not complete within <b>{{ formattedHold }}</b>.
    </p>

    <div class="confirm-bay__trust">
      <span>
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" /></svg>
        TLS 1.3
      </span>
      <span>
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6z" /></svg>
        PCI-DSS
      </span>
      <span>
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 17l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" /></svg>
        3-D Secure
      </span>
    </div>
  </section>
</template>

<style scoped>
.confirm-bay__header {
  padding-bottom: 0;
  border-bottom: none;
  margin-bottom: var(--space-md);
}

.confirm-bay__check {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  cursor: pointer;
  padding: 0.55rem 0;
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.5;
}

.confirm-bay__check-box {
  appearance: none;
  width: 1.1rem;
  height: 1.1rem;
  border: var(--border-hairline) solid var(--outline);
  border-radius: var(--radius-sm);
  background-color: var(--surface-container-low);
  flex-shrink: 0;
  margin-top: 0.1rem;
  position: relative;
  cursor: pointer;
}

.confirm-bay__check-box:checked {
  background-color: var(--secondary);
  border-color: var(--secondary);
}

.confirm-bay__check-box:checked::after {
  content: '';
  position: absolute;
  left: 0.25rem;
  top: 0.0625rem;
  width: 0.25rem;
  height: 0.5rem;
  border: solid var(--surface);
  border-width: 0 0.125rem 0.125rem 0;
  transform: rotate(45deg);
}

.confirm-bay__link {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}

.confirm-bay__pay {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 3.5rem;
  padding: 0 1.25rem;
  margin-top: var(--space-md);
  border-radius: var(--radius-sm);
  background-color: var(--secondary);
  color: var(--surface);
  border: none;
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: 0.01em;
  width: 100%;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    transform var(--duration-micro) var(--ease-standard);
}

.confirm-bay__pay:hover:not(:disabled) {
  background-color: var(--secondary-hover);
}

.confirm-bay__pay:active:not(:disabled) {
  transform: scale(0.99);
}

.confirm-bay__pay:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.confirm-bay__pay-amt {
  font-variant-numeric: tabular-nums;
}

.confirm-bay__note {
  margin: 0.75rem 0 0;
  font-family: var(--font-body);
  font-size: 0.6875rem;
  line-height: 1.5;
  color: var(--on-tertiary-fixed-variant);
  letter-spacing: 0.04em;
}

.confirm-bay__note b {
  color: var(--secondary);
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.confirm-bay__trust {
  display: flex;
  gap: var(--space-md);
  padding-top: var(--space-sm);
  margin-top: var(--space-md);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
  font-family: var(--font-body);
  font-size: 0.625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  align-items: center;
  flex-wrap: wrap;
}

.confirm-bay__trust span {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.confirm-bay__trust svg {
  width: 0.75rem;
  height: 0.75rem;
  color: var(--secondary);
}
</style>
