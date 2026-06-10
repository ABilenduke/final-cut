<script setup lang="ts">
import { useJobOpenings } from '~/composables/useJobOpenings'

// Admin-managed openings (admin-v2 Plan 13); benefits/intro stay editorial copy.
const { data: openingsData } = useJobOpenings()
const openings = computed(() => openingsData.value?.data ?? [])

const benefits = [
  'Free movie tickets for you and a guest',
  'Discounted food and beverages',
  'Flexible scheduling',
  'Loyalty program Premier membership',
  'Career development and training',
  'A team that genuinely loves film',
]

useHead(() => ({
  title: 'Careers — Final Cut',
  meta: [
    { name: 'description', content: 'Join the Final Cut team. Current openings in operations, guest services, and food & beverage.' },
  ],
  script: openings.value.map(opening => ({
    type: 'application/ld+json',
    innerHTML: safeJsonLd({
      '@context': 'https://schema.org',
      '@type': 'JobPosting',
      title: opening.title,
      description: opening.description,
      employmentType: opening.type === 'Full-time' ? 'FULL_TIME' : 'PART_TIME',
      hiringOrganization: {
        '@type': 'Organization',
        name: 'Final Cut',
      },
    }),
  })),
}))
</script>

<template>
  <div class="careers-page">
    <div class="close-up">
      <h1 class="careers-page__title display-sm">Careers</h1>
      <p class="careers-page__intro body-lg">
        Final Cut is built by people who care about the details — the sound, the picture, the food, and the way a guest feels the moment they walk in. If that sounds like you, we'd like to hear from you.
      </p>

      <!-- Current Openings -->
      <section class="careers-page__section">
        <h2 class="careers-page__section-heading headline-md">Current Openings</h2>
        <div class="careers-page__openings">
          <CvCard v-for="opening in openings" :key="opening.title" class="careers-page__opening">
            <div class="careers-page__opening-header">
              <h3 class="careers-page__opening-title title-lg">{{ opening.title }}</h3>
              <div class="careers-page__opening-tags">
                <CvBadge size="sm">{{ opening.department }}</CvBadge>
                <CvBadge size="sm">{{ opening.type }}</CvBadge>
              </div>
            </div>
            <p class="careers-page__opening-desc body-sm">{{ opening.description }}</p>
          </CvCard>
        </div>
      </section>

      <!-- Benefits -->
      <section class="careers-page__section">
        <h2 class="careers-page__section-heading headline-md">Why Work Here</h2>
        <ul class="careers-page__benefits">
          <li
            v-for="benefit in benefits"
            :key="benefit"
            class="careers-page__benefit body-md"
          >
            {{ benefit }}
          </li>
        </ul>
      </section>

      <!-- How to Apply -->
      <section class="careers-page__section">
        <h2 class="careers-page__section-heading headline-md">How to Apply</h2>
        <p class="careers-page__apply body-md">
          Send your resume and a brief note about why you'd be a good fit to
          <a href="mailto:careers@finalcut.test" class="careers-page__link">careers@finalcut.test</a>.
          Include the position title in your subject line.
        </p>
      </section>
    </div>
  </div>
</template>

<style scoped>
.careers-page {
  padding-block: var(--space-3xl);
}

.careers-page__title {
  color: var(--on-surface);
  margin: 0 0 var(--space-md);
}

.careers-page__intro {
  color: var(--tertiary);
  margin: 0 0 var(--space-2xl);
  line-height: 1.7;
}

.careers-page__section {
  margin-bottom: var(--space-2xl);
}

.careers-page__section-heading {
  color: var(--on-surface);
  margin: 0 0 var(--space-lg);
}

.careers-page__openings {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.careers-page__opening-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-sm);
  margin-bottom: var(--space-sm);
}

.careers-page__opening-title {
  color: var(--on-surface);
  margin: 0;
}

.careers-page__opening-tags {
  display: flex;
  gap: var(--space-xs);
}

.careers-page__opening-desc {
  color: var(--tertiary);
  margin: 0;
  line-height: 1.5;
}

.careers-page__benefits {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.careers-page__benefit {
  color: var(--on-surface);
  padding-left: var(--space-lg);
  position: relative;
}

.careers-page__benefit::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.5em;
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
  background-color: var(--secondary);
}

.careers-page__apply {
  color: var(--on-surface);
  margin: 0;
  line-height: 1.7;
}

.careers-page__link {
  color: var(--secondary);
  text-decoration: underline;
  text-underline-offset: 0.125rem;
}
</style>
