<script setup lang="ts">
const props = defineProps<{
  fullName: string
  email: string
  phone: string
  reelSocietyId: string
  isAuthenticated?: boolean
}>()

const emit = defineEmits<{
  'update:fullName': [value: string]
  'update:email': [value: string]
  'update:phone': [value: string]
  'update:reelSocietyId': [value: string]
  'sign-in': []
}>()

const fullNameModel = computed<string>({
  get: () => props.fullName,
  set: (v) => emit('update:fullName', v),
})
const emailModel = computed<string>({
  get: () => props.email,
  set: (v) => emit('update:email', v),
})
const phoneModel = computed<string>({
  get: () => props.phone,
  set: (v) => emit('update:phone', v),
})
const reelModel = computed<string>({
  get: () => props.reelSocietyId,
  set: (v) => emit('update:reelSocietyId', v),
})
</script>

<template>
  <section class="bay">
    <header class="bay__header">
      <div>
        <div class="bay__number">§ 01</div>
        <h2 class="bay__title">Your <em>contact.</em></h2>
        <p class="bay__sub">Your ticket and reel notes will be emailed here.</p>
      </div>
      <span class="bay__badge">Members save $2 / ticket</span>
    </header>

    <div v-if="!isAuthenticated" class="contact-bay__auth">
      <button type="button" class="contact-bay__auth-btn" @click="emit('sign-in')">
        <svg
          class="contact-bay__auth-icon"
          viewBox="0 0 24 24"
          fill="currentColor"
          aria-hidden="true"
        ><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-3.33 0-10 1.67-10 5v3h20v-3c0-3.33-6.67-5-10-5z" /></svg>
        Sign in to Reel Society
      </button>
      <button
        type="button"
        class="contact-bay__auth-btn contact-bay__auth-btn--active"
        :aria-pressed="true"
      >
        <svg
          class="contact-bay__auth-icon"
          viewBox="0 0 24 24"
          fill="currentColor"
          aria-hidden="true"
        ><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
        Continue as guest
      </button>
      <p class="contact-bay__auth-hint">
        Members save <b>$2</b> per ticket and get a 48-hour early access window.
      </p>
    </div>

    <div v-if="!isAuthenticated" class="bay__divider">or continue below</div>

    <div class="checkout-fields">
      <div class="checkout-row-2">
        <CvInput
          v-model="fullNameModel"
          label="Full name"
          placeholder="Jane Appleseed"
          required
          autocomplete="name"
        />
        <CvInput
          v-model="emailModel"
          type="email"
          label="Email"
          placeholder="you@example.com"
          required
          autocomplete="email"
        />
      </div>
      <div class="checkout-row-2">
        <CvInput
          v-model="phoneModel"
          type="tel"
          label="Phone"
          placeholder="+1 (555) 000-0000"
          help-text="For SMS show reminders. Reply STOP to opt out."
          autocomplete="tel"
        />
        <CvInput
          v-model="reelModel"
          label="Reel Society ID (optional)"
          placeholder="FC-000-0000"
          help-text="Member? Discount applies at review."
        />
      </div>
    </div>
  </section>
</template>

<style scoped>
.contact-bay__auth {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-sm);
}

.contact-bay__auth-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  min-height: 3rem;
  padding: 0.85rem 1rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  font-size: 0.875rem;
  color: var(--on-surface);
  background-color: var(--surface-container-low);
  cursor: pointer;
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
}

.contact-bay__auth-icon {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

.contact-bay__auth-btn:hover,
.contact-bay__auth-btn:focus-visible {
  border-color: var(--secondary);
  color: var(--secondary);
}

.contact-bay__auth-btn--active {
  border-color: rgb(var(--secondary-rgb) / 0.4);
  color: var(--secondary);
}

.contact-bay__auth-hint {
  grid-column: 1 / -1;
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--on-tertiary-fixed-variant);
  text-align: center;
  margin: 0.35rem 0 0;
  letter-spacing: 0.04em;
}

.contact-bay__auth-hint b {
  color: var(--secondary);
  font-weight: 500;
}

@media (max-width: 40rem) {
  .contact-bay__auth {
    grid-template-columns: 1fr;
  }
}
</style>
