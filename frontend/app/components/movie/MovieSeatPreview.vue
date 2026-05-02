<script setup lang="ts">
import type { Movie } from '~/types/movie'

const props = defineProps<{
  movie: Movie
}>()

// TODO(backend): replace with real seat-availability data pulled from the showtime detail API.
// This component is a visual teaser inside the movie detail page. The canonical seat selection
// experience lives at /purchase/:showtimeId.
type SeatKind = 'taken' | 'sel' | 'member' | 'available' | 'gap'
type SeatRow = { label: string; cells: SeatKind[] }

const rows = computed<SeatRow[]>(() => {
  const rowLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M']
  return rowLabels.map((r, ri) => {
    const len = ri < 2 ? 14 : ri < 8 ? 18 : 20
    const mid = Math.floor(len / 2)
    const cells: SeatKind[] = []
    for (let i = 0; i < len; i++) {
      if (i === mid) {
        cells.push('gap')
      }
      const taken = ((i * 3 + ri * 5) % 11) < 4
      const memberBand = (r === 'E' || r === 'F' || r === 'G') && i >= mid - 3 && i <= mid + 3
      if (r === 'F' && (i === 6 || i === 7)) {
        cells.push('sel')
      } else if (memberBand) {
        cells.push('member')
      } else if (taken) {
        cells.push('taken')
      } else {
        cells.push('available')
      }
    }
    return { label: r, cells }
  })
})

// TODO(backend): replace with real order totals from the cart.
const orderPreview = {
  lineItems: [
    { label: '2 × Standard · F-7, F-8', amount: 3700 },
    { label: '1 × Brown Butter Popcorn · L', amount: 900 },
    { label: 'Member discount', amount: -400 },
  ],
  total: 4200,
}

const summaryTitle = computed(() => props.movie.title)
</script>

<template>
  <div class="movie-seat-preview">
    <div class="movie-seat-preview__left">
      <div class="bay-eyebrow">Auditorium 01 · Screen preview</div>
      <h4 class="movie-seat-preview__h4">
        The room, as it stands. <em>Reserved seating.</em>
      </h4>
      <p class="movie-seat-preview__p">
        Center-row seats in rows E — G are currently held for Reel Society members. Release happens 30 minutes before showtime if unclaimed.
      </p>
      <div class="movie-seat-preview__screen-vis">
        <div class="movie-seat-preview__screen-bar" aria-hidden="true" />
        <div class="movie-seat-preview__screen-label">Screen · 15 m · 2.39:1</div>
        <div class="movie-seat-preview__rows" aria-hidden="true">
          <div v-for="row in rows" :key="row.label" class="movie-seat-preview__row">
            <span class="movie-seat-preview__lbl">{{ row.label }}</span>
            <span
              v-for="(cell, i) in row.cells"
              :key="i"
              class="movie-seat-preview__seat"
              :class="`movie-seat-preview__seat--${cell}`"
            />
            <span class="movie-seat-preview__lbl">{{ row.label }}</span>
          </div>
        </div>
        <div class="movie-seat-preview__legend">
          <span><i class="movie-seat-preview__i movie-seat-preview__i--free" /> Available</span>
          <span><i class="movie-seat-preview__i movie-seat-preview__i--sel" /> Selected</span>
          <span><i class="movie-seat-preview__i movie-seat-preview__i--member" /> Members row</span>
          <span><i class="movie-seat-preview__i movie-seat-preview__i--taken" /> Taken</span>
        </div>
      </div>
    </div>

    <aside class="movie-seat-preview__summary" aria-label="Order preview (sample)">
      <div class="movie-seat-preview__sum-h">
        <span>Order · Preview</span>
        <b>Draft</b>
      </div>
      <div class="movie-seat-preview__sum-t">
        {{ summaryTitle }}
        <br>
        <span class="movie-seat-preview__sum-meta">
          Showtime details at selection
        </span>
      </div>
      <div
        v-for="(item, i) in orderPreview.lineItems"
        :key="i"
        class="movie-seat-preview__sum-row"
      >
        <span>{{ item.label }}</span>
        <span :class="{ 'movie-seat-preview__sum-discount': item.amount < 0 }">
          <template v-if="item.amount < 0">−</template>{{ formatCurrency(Math.abs(item.amount)) }}
        </span>
      </div>
      <div class="movie-seat-preview__sum-row movie-seat-preview__sum-row--total">
        <span>Total</span>
        <b>{{ formatCurrency(orderPreview.total) }}</b>
      </div>
      <button type="button" class="btn-gold movie-seat-preview__cta">
        Continue to Checkout &nbsp;→
      </button>
      <p class="movie-seat-preview__sum-foot">
        Seats held for 8 minutes. Reserved seating, no late entry after 10 min. Members receive a $2/ticket discount.
      </p>
    </aside>
  </div>
</template>

<style scoped>
.movie-seat-preview {
  margin-top: var(--space-2xl);
  padding: var(--space-xl);
  background: var(--surface-container);
  border-radius: var(--radius-card);
  display: grid;
  grid-template-columns: 1fr 20rem;
  gap: var(--space-xl);
}

.movie-seat-preview__h4 {
  font-family: var(--font-display);
  font-size: 1.5rem;
  letter-spacing: -0.015em;
  font-weight: 500;
  color: var(--on-surface);
  margin: 0 0 var(--space-sm);
}

.movie-seat-preview__h4 em {
  font-style: italic;
  color: var(--tertiary);
}

.movie-seat-preview__p {
  color: var(--tertiary);
  font-size: 0.9375rem;
  line-height: 1.55;
  max-width: 44ch;
  margin: 0 0 var(--space-md);
}

/* ——— Screen visualisation ——— */
.movie-seat-preview__screen-vis {
  background: var(--surface-container-lowest);
  padding: var(--space-md);
  border-radius: var(--radius-card);
  border: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.2);
}

.movie-seat-preview__screen-bar {
  height: 0.375rem;
  background: linear-gradient(90deg, transparent, var(--secondary), transparent);
  border-radius: 0.1875rem;
  margin-bottom: 0.25rem;
  opacity: 0.7;
}

.movie-seat-preview__screen-label {
  text-align: center;
  font-size: 0.5625rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  margin-bottom: var(--space-md);
}

.movie-seat-preview__rows {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  align-items: center;
}

.movie-seat-preview__row {
  display: flex;
  gap: 0.2rem;
  align-items: center;
}

.movie-seat-preview__lbl {
  font-size: 0.5625rem;
  letter-spacing: 0.2em;
  color: var(--on-tertiary-fixed-variant);
  width: 1rem;
  text-align: center;
}

.movie-seat-preview__seat {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--surface-container-high);
  display: inline-block;
}

.movie-seat-preview__seat--taken {
  background: var(--outline-variant);
  opacity: 0.4;
}

.movie-seat-preview__seat--sel {
  background: var(--secondary);
}

.movie-seat-preview__seat--member {
  background: rgba(var(--secondary-rgb), 0.25);
  border: var(--border-hairline) solid rgba(var(--secondary-rgb), 0.5);
}

.movie-seat-preview__seat--gap {
  background: transparent;
}

.movie-seat-preview__legend {
  display: flex;
  gap: var(--space-md);
  margin-top: var(--space-md);
  font-size: 0.6875rem;
  color: var(--on-tertiary-fixed-variant);
  flex-wrap: wrap;
}

.movie-seat-preview__legend span {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.movie-seat-preview__i {
  display: inline-block;
  width: 0.7rem;
  height: 0.7rem;
  border-radius: var(--radius-sm);
}

.movie-seat-preview__i--free { background: var(--surface-container-high); }
.movie-seat-preview__i--taken { background: var(--outline-variant); opacity: 0.6; }
.movie-seat-preview__i--sel { background: var(--secondary); }
.movie-seat-preview__i--member {
  background: rgba(var(--secondary-rgb), 0.25);
  border: var(--border-hairline) solid rgba(var(--secondary-rgb), 0.5);
}

/* ——— Summary card ——— */
.movie-seat-preview__summary {
  padding: var(--space-lg);
  background: var(--surface-container-high);
  border-radius: var(--radius-card);
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  border: var(--border-hairline) solid rgba(var(--secondary-rgb), 0.15);
}

.movie-seat-preview__sum-h {
  display: flex;
  justify-content: space-between;
  font-size: 0.625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.movie-seat-preview__sum-h b {
  color: var(--secondary);
  font-weight: 500;
}

.movie-seat-preview__sum-t {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  line-height: 1.1;
  letter-spacing: -0.015em;
}

.movie-seat-preview__sum-meta {
  font-size: 0.875rem;
  color: var(--on-tertiary-fixed-variant);
  font-family: var(--font-body);
  font-style: normal;
  letter-spacing: 0.06em;
  font-weight: 400;
}

.movie-seat-preview__sum-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 0.875rem;
  color: var(--tertiary);
  border-top: var(--border-hairline) solid rgba(var(--outline-variant-rgb), 0.25);
  padding-top: 0.6rem;
}

.movie-seat-preview__sum-discount {
  color: var(--secondary);
}

.movie-seat-preview__sum-row--total {
  border-top: var(--border-hairline) solid rgba(var(--secondary-rgb), 0.3);
  padding-top: 0.75rem;
  font-size: 1rem;
  color: var(--on-surface);
}

.movie-seat-preview__sum-row--total b {
  font-family: var(--font-display);
  font-size: 1.5rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: -0.01em;
}

.movie-seat-preview__cta {
  width: 100%;
}

.movie-seat-preview__sum-foot {
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  color: var(--on-tertiary-fixed-variant);
  line-height: 1.5;
  margin: 0;
}

@media (max-width: 68.75rem) {
  .movie-seat-preview {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 39.999rem) {
  .movie-seat-preview__row {
    /* Scale down seats on very narrow screens */
    --seat-size: 0.625rem;
  }
  .movie-seat-preview__seat {
    width: 0.625rem;
    height: 0.625rem;
  }
}
</style>
