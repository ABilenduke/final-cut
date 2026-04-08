<script setup lang="ts">
import type { Booking } from '~/types/booking'

const props = defineProps<{
  orders: Booking[]
  total: number
  currentPage: number
  perPage: number
}>()

const emit = defineEmits<{
  'page-change': [page: number]
}>()

const totalPages = computed(() => Math.ceil(props.total / props.perPage))
const hasPrev = computed(() => props.currentPage > 1)
const hasNext = computed(() => props.currentPage < totalPages.value)

function statusVariant(status: Booking['status']): 'default' | 'accent' | 'warning' {
  switch (status) {
    case 'confirmed': return 'accent'
    case 'cancelled': return 'warning'
    case 'refunded': return 'default'
  }
}

function paymentLabel(method: Booking['paymentMethod']): string {
  switch (method) {
    case 'card': return 'Card'
    case 'gift_card': return 'Gift Card'
    case 'mixed': return 'Card + Gift Card'
  }
}
</script>

<template>
  <div class="order-history">
    <div v-if="orders.length === 0" class="order-history__empty">
      <p class="order-history__empty-text body-md">No orders yet.</p>
    </div>

    <div v-else class="order-history__list">
      <CvAccordion
        v-for="order in orders"
        :id="`order-${order.id}`"
        :key="order.id"
        :title="`${formatDate(order.createdAt)} — ${order.movieTitle} — ${formatCurrency(order.total)}`"
      >
        <div class="order-history__detail">
          <div class="order-history__row">
            <span class="order-history__label label-md">Confirmation</span>
            <span class="order-history__value body-md">{{ order.confirmationCode }}</span>
          </div>

          <div class="order-history__row">
            <span class="order-history__label label-md">Status</span>
            <CvBadge :variant="statusVariant(order.status)" size="sm">
              {{ order.status }}
            </CvBadge>
          </div>

          <div class="order-history__row">
            <span class="order-history__label label-md">Payment</span>
            <span class="order-history__value body-md">{{ paymentLabel(order.paymentMethod) }}</span>
          </div>

          <div class="order-history__section">
            <h4 class="order-history__section-title label-lg">Seats</h4>
            <ul class="order-history__items">
              <li v-for="seat in order.seats" :key="seat.seatId" class="order-history__item body-sm">
                {{ seat.seatId }} ({{ seat.section }}) — {{ formatCurrency(seat.price) }}
              </li>
            </ul>
          </div>

          <div v-if="order.foodItems.length > 0" class="order-history__section">
            <h4 class="order-history__section-title label-lg">Food & Drink</h4>
            <ul class="order-history__items">
              <li v-for="item in order.foodItems" :key="item.itemId" class="order-history__item body-sm">
                {{ item.name }} &times; {{ item.quantity }} — {{ formatCurrency(item.totalPrice) }}
              </li>
            </ul>
          </div>
        </div>
      </CvAccordion>
    </div>

    <div v-if="totalPages > 1" class="order-history__pagination">
      <CvButton
        variant="tertiary"
        :disabled="!hasPrev"
        @click="emit('page-change', currentPage - 1)"
      >
        Previous
      </CvButton>
      <span class="order-history__page-info label-md">
        Page {{ currentPage }} of {{ totalPages }}
      </span>
      <CvButton
        variant="tertiary"
        :disabled="!hasNext"
        @click="emit('page-change', currentPage + 1)"
      >
        Next
      </CvButton>
    </div>
  </div>
</template>

<style scoped>
.order-history {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.order-history__empty {
  padding: var(--space-xl);
  text-align: center;
}

.order-history__empty-text {
  color: var(--tertiary);
  margin: 0;
}

.order-history__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.order-history__detail {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding-top: var(--space-sm);
}

.order-history__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.order-history__label {
  color: var(--tertiary);
}

.order-history__value {
  color: var(--on-surface);
}

.order-history__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.order-history__section-title {
  color: var(--on-surface);
  margin: 0;
}

.order-history__items {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-xs);
}

.order-history__item {
  color: var(--tertiary);
}

.order-history__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-md);
  padding-top: var(--space-md);
}

.order-history__page-info {
  color: var(--tertiary);
}
</style>
