<script setup lang="ts">
import { useScreeningPackages } from '~/composables/useScreeningPackages'
import { usePrivateScreeningsCopy, resolvePrivateScreeningsCopy } from '~/composables/useSiteContent'

// Built-in page intro — also the fallback when no admin edit exists yet (G3).
const FALLBACK_COPY = {
  title: 'Private Screenings & Events',
  intro: 'From birthdays to boardrooms, Final Cut is the perfect venue for your next event. Choose a package below or tell us what you have in mind.',
}

// Admin-managed intro copy (admin-v6 G3).
const { data: copyData } = usePrivateScreeningsCopy()
const copy = computed(() =>
  resolvePrivateScreeningsCopy(copyData.value?.data?.privateScreenings ?? null, FALLBACK_COPY),
)

useHead(() => ({
  title: `${copy.value.title} — Final Cut`,
  meta: [
    { name: 'description', content: 'Host your next birthday, corporate event, or private screening at Final Cut.' },
  ],
}))

// Admin-managed packages (admin-v2 Plan 14).
const { data: packagesData } = useScreeningPackages()
const packages = computed(() => packagesData.value?.data ?? [])
</script>

<template>
  <div class="screenings-page">
    <div class="container">
      <h1 class="screenings-page__title display-sm">{{ copy.title }}</h1>
      <p class="screenings-page__intro body-lg">{{ copy.intro }}</p>
      <div class="rack-focus">
        <div class="rack-focus__secondary">
          <RentalInquiryForm />
        </div>
        <div class="rack-focus__primary">
          <div class="screenings-page__packages">
            <PackageCard
              v-for="pkg in packages"
              :key="pkg.name"
              :package="pkg"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.screenings-page {
  padding: var(--space-3xl) 0;
}

.screenings-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-md);
}

.screenings-page__intro {
  color: var(--tertiary);
  margin: 0 0 var(--space-2xl);
  max-width: 45rem;
  line-height: 1.6;
}

.screenings-page__packages {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}
</style>
