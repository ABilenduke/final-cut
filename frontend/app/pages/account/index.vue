<script setup lang="ts">
definePageMeta({ layout: 'account', middleware: 'auth' })
useHead({
  title: 'Dashboard — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const { profile, orders, bookings, loyalty } = useAccount()

const { data: profileData } = await profile()
const { data: ordersData } = await orders(1, 5)
const { data: bookingsData } = await bookings()
const { data: loyaltyData } = await loyalty()
</script>

<template>
  <div class="dashboard">
    <h1 class="dashboard__title">Dashboard</h1>

    <div class="dashboard__grid">
      <div class="dashboard__main">
        <section class="dashboard__section">
          <h2 class="dashboard__section-title">Upcoming Bookings</h2>
          <UpcomingBookings :bookings="bookingsData?.data ?? []" />
        </section>

        <section class="dashboard__section">
          <h2 class="dashboard__section-title">Recent Orders</h2>
          <OrderHistoryList
            :orders="ordersData?.data ?? []"
            :total="ordersData?.meta?.total ?? 0"
            :current-page="1"
            :per-page="5"
            @page-change="navigateTo('/account/orders')"
          />
        </section>
      </div>

      <aside class="dashboard__sidebar">
        <CvCard variant="high" class="dashboard__profile-card">
          <div class="dashboard__avatar">
            {{ profileData?.data?.name?.charAt(0)?.toUpperCase() ?? '?' }}
          </div>
          <p class="dashboard__profile-name">{{ profileData?.data?.name }}</p>
          <p class="dashboard__profile-email">{{ profileData?.data?.email }}</p>
        </CvCard>

        <LoyaltyPointsCard
          v-if="loyaltyData?.data"
          :points="loyaltyData.data.points"
          :tier="(loyaltyData.data.tier as 'member' | 'premier')"
          :premier-expiry="loyaltyData.data.premierExpiry"
        />

        <nav class="dashboard__quick-links" aria-label="Quick actions">
          <CvButton variant="tertiary" href="/account/profile">Edit Profile</CvButton>
          <CvButton variant="tertiary" href="/account/payment-methods">Payment Methods</CvButton>
        </nav>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.dashboard__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-lg);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-xl);
}

.dashboard__grid {
  display: grid;
  gap: var(--space-2xl);
}

@media (min-width: 60rem) {
  .dashboard__grid {
    grid-template-columns: 65fr 35fr;
  }
}

.dashboard__main {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
  min-width: 0;
}

.dashboard__sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.dashboard__section-title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-md);
}

.dashboard__profile-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-sm);
  text-align: center;
}

.dashboard__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 5rem;
  height: 5rem;
  border-radius: 50%;
  background-color: var(--primary-container);
  color: var(--primary);
  font-family: var(--font-display);
  font-size: var(--type-headline-md);
  line-height: 1;
}

.dashboard__profile-name {
  font-family: var(--font-body);
  font-size: var(--type-body-lg);
  color: var(--on-surface);
}

.dashboard__profile-email {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--tertiary);
}

.dashboard__quick-links {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}
</style>
