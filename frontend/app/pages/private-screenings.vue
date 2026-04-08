<script setup lang="ts">
useHead({
  title: 'Private Screenings & Events — Final Cut',
  meta: [
    { name: 'description', content: 'Host your next birthday, corporate event, or private screening at Final Cut.' },
  ],
})

const packages = [
  {
    name: 'Birthday Party',
    description: 'Celebrate with a private screening for up to 30 guests. Perfect for kids and adults alike.',
    startingPrice: 35000,
    features: [
      'Private auditorium for 2 hours',
      'Custom birthday message on screen',
      'Popcorn & drinks for all guests',
      'Dedicated party host',
      'Choice of current or classic film',
    ],
  },
  {
    name: 'Corporate Event',
    description: 'Team building, product launches, client entertainment, or company milestones.',
    startingPrice: 75000,
    features: [
      'Full auditorium rental',
      'Presentation mode with HDMI input',
      'AV equipment and microphone',
      'Catering packages available',
      'Flexible scheduling including weekdays',
    ],
  },
  {
    name: 'Proposal Package',
    description: 'Pop the question on the big screen. An unforgettable moment in a one-of-a-kind setting.',
    startingPrice: 50000,
    features: [
      'Private auditorium',
      'Custom on-screen message or video',
      'Champagne and chocolates',
      'Professional photo opportunity',
      'Choice of film or personal media',
    ],
  },
  {
    name: 'Custom Event',
    description: 'Something else in mind? We can tailor a package to fit your vision.',
    startingPrice: 40000,
    features: [
      'Flexible auditorium options',
      'Custom AV setup',
      'Food & beverage packages',
      'Event coordination support',
      'Pricing based on requirements',
    ],
  },
]
</script>

<template>
  <div class="screenings-page">
    <div class="container">
      <h1 class="screenings-page__title display-sm">Private Screenings & Events</h1>
      <p class="screenings-page__intro body-lg">
        From birthdays to boardrooms, Final Cut is the perfect venue for your next event. Choose a package below or tell us what you have in mind.
      </p>
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
