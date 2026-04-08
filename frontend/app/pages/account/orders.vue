<script setup lang="ts">
definePageMeta({ layout: 'account', middleware: 'auth' })
useHead({
  title: 'Order History — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const route = useRoute()
const { orders } = useAccount()

const currentPage = computed(() => {
  const p = Number(route.query.page)
  return Number.isFinite(p) && p > 0 ? p : 1
})

const perPage = 10

const { data: initialOrdersData } = await orders(currentPage.value, perPage)
const ordersData = ref(initialOrdersData.value)

watch(currentPage, async (page) => {
  const { data } = await orders(page, perPage)
  ordersData.value = data.value
})

function handlePageChange(page: number) {
  navigateTo({ query: { ...route.query, page } })
}
</script>

<template>
  <div class="orders-page">
    <h1 class="orders-page__title">Order History</h1>

    <div class="orders-page__content">
      <OrderHistoryList
        :orders="ordersData?.data ?? []"
        :total="ordersData?.meta?.total ?? 0"
        :current-page="currentPage"
        :per-page="perPage"
        @page-change="handlePageChange"
      />
    </div>
  </div>
</template>

<style scoped>
.orders-page__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-lg);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-xl);
}

.orders-page__content {
  max-width: 40rem;
  margin-inline: auto;
}
</style>
