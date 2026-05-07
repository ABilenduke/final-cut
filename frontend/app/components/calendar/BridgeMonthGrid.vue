<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'
import { toLocalDateKey } from '~/utils/formatDate'

const props = defineProps<{
  events: CalendarEvent[]
  selectedDate: string
  todayDate: string
  month: number
  year: number
}>()

const emit = defineEmits<{
  'select-date': [date: string]
}>()

interface Cell {
  date: string
  dayNumber: number
  outsideMonth: boolean
}

const DOW_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const monthLabel = computed(() =>
  new Date(props.year, props.month - 1, 1).toLocaleDateString('en-US', { month: 'long' }),
)

const screeningCount = computed(
  () => props.events.filter((e) => e.type === 'showtime').length,
)

const specialCount = computed(
  () => props.events.filter((e) => e.type === 'special_event').length,
)

const cells = computed<Cell[]>(() => {
  const first = new Date(props.year, props.month - 1, 1)
  // JS Date.getDay returns 0 for Sunday, 1 for Monday, …; we want Monday-start.
  const firstDow = (first.getDay() + 6) % 7

  const daysInMonth = new Date(props.year, props.month, 0).getDate()

  let prevMonth = props.month - 1
  let prevYear = props.year
  if (prevMonth < 1) {
    prevMonth = 12
    prevYear -= 1
  }
  const daysInPrevMonth = new Date(prevYear, prevMonth, 0).getDate()

  let nextMonth = props.month + 1
  let nextYear = props.year
  if (nextMonth > 12) {
    nextMonth = 1
    nextYear += 1
  }

  const out: Cell[] = []

  for (let i = firstDow; i > 0; i -= 1) {
    const day = daysInPrevMonth - i + 1
    out.push({
      date: toLocalDateKey(prevYear, prevMonth, day),
      dayNumber: day,
      outsideMonth: true,
    })
  }
  for (let day = 1; day <= daysInMonth; day += 1) {
    out.push({
      date: toLocalDateKey(props.year, props.month, day),
      dayNumber: day,
      outsideMonth: false,
    })
  }
  // Pad to 35 (5 weeks) minimum, 42 if month spans 6 weeks.
  const target = out.length <= 35 ? 35 : 42
  let padDay = 1
  while (out.length < target) {
    out.push({
      date: toLocalDateKey(nextYear, nextMonth, padDay),
      dayNumber: padDay,
      outsideMonth: true,
    })
    padDay += 1
  }
  return out
})

const eventsByDate = computed(() => {
  const map = new Map<string, CalendarEvent[]>()
  for (const event of props.events) {
    const list = map.get(event.date) ?? []
    list.push(event)
    map.set(event.date, list)
  }
  for (const [, list] of map) {
    list.sort((a, b) => a.startTime.localeCompare(b.startTime))
  }
  return map
})

const focusedDate = ref<string>(props.selectedDate)

watch(() => props.selectedDate, (next) => {
  focusedDate.value = next
})

const gridEl = ref<HTMLElement | null>(null)

function focusCell(date: string) {
  focusedDate.value = date
  nextTick(() => {
    if (!gridEl.value) return
    const el = gridEl.value.querySelector<HTMLElement>(`[data-date="${date}"]`)
    el?.focus()
  })
}

function moveFocus(direction: 'left' | 'right' | 'up' | 'down') {
  const idx = cells.value.findIndex((c) => c.date === focusedDate.value)
  if (idx === -1) return
  const delta = direction === 'left' ? -1 : direction === 'right' ? 1 : direction === 'up' ? -7 : 7
  let target = idx + delta
  while (target >= 0 && target < cells.value.length) {
    const cell = cells.value[target]!
    if (!cell.outsideMonth) {
      focusCell(cell.date)
      return
    }
    target += delta
  }
}

function onGridKeydown(event: KeyboardEvent) {
  switch (event.key) {
    case 'ArrowLeft':
      event.preventDefault()
      moveFocus('left')
      break
    case 'ArrowRight':
      event.preventDefault()
      moveFocus('right')
      break
    case 'ArrowUp':
      event.preventDefault()
      moveFocus('up')
      break
    case 'ArrowDown':
      event.preventDefault()
      moveFocus('down')
      break
    case 'Home': {
      event.preventDefault()
      const idx = cells.value.findIndex((c) => c.date === focusedDate.value)
      const startOfRow = Math.floor(idx / 7) * 7
      for (let i = startOfRow; i < startOfRow + 7; i += 1) {
        const cell = cells.value[i]
        if (cell && !cell.outsideMonth) {
          focusCell(cell.date)
          break
        }
      }
      break
    }
    case 'End': {
      event.preventDefault()
      const idx = cells.value.findIndex((c) => c.date === focusedDate.value)
      const endOfRow = Math.floor(idx / 7) * 7 + 6
      for (let i = endOfRow; i >= endOfRow - 6; i -= 1) {
        const cell = cells.value[i]
        if (cell && !cell.outsideMonth) {
          focusCell(cell.date)
          break
        }
      }
      break
    }
  }
}

function onSelect(date: string) {
  emit('select-date', date)
}
</script>

<template>
  <section class="bridge-month-grid" aria-label="Month calendar">
    <header class="bridge-month-grid__toolbar">
      <div class="bridge-month-grid__title">
        {{ monthLabel }}
        <em class="bridge-month-grid__year">· {{ year }}</em>
      </div>
      <div class="bridge-month-grid__counters" aria-hidden="true">
        <span><b>{{ screeningCount }}</b> screenings · <b>{{ specialCount }}</b> specials</span>
      </div>
    </header>

    <div class="bridge-month-grid__dow" aria-hidden="true">
      <span v-for="d in DOW_LABELS" :key="d">{{ d }}</span>
    </div>

    <div
      ref="gridEl"
      class="bridge-month-grid__cells"
      role="grid"
      :aria-label="`${monthLabel} ${year}`"
      @keydown="onGridKeydown"
    >
      <BridgeDayCell
        v-for="cell in cells"
        :key="cell.date"
        :date="cell.date"
        :day-number="cell.dayNumber"
        :events="eventsByDate.get(cell.date) ?? []"
        :selected="cell.date === selectedDate"
        :today="cell.date === todayDate"
        :outside-month="cell.outsideMonth"
        :focused="cell.date === focusedDate"
        :data-date="cell.date"
        @select="onSelect"
      />
    </div>
  </section>
</template>

<style scoped>
.bridge-month-grid {
  background-color: var(--surface-container-lowest);
  border-radius: var(--radius-card);
  overflow: hidden;
}

.bridge-month-grid__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-md) var(--space-lg);
  border-bottom: var(--border-hairline) solid rgba(87, 66, 62, 0.18);
  flex-wrap: wrap;
  gap: var(--space-sm);
}

.bridge-month-grid__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  letter-spacing: -0.01em;
  color: var(--on-surface);
}

.bridge-month-grid__year {
  color: var(--on-tertiary-fixed-variant);
  font-style: italic;
  font-size: 0.75em;
  letter-spacing: 0.04em;
  margin-left: 0.4rem;
}

.bridge-month-grid__counters {
  font-size: var(--type-label-sm);
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.bridge-month-grid__counters b {
  color: var(--secondary);
  font-weight: 500;
}

.bridge-month-grid__dow {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background-color: var(--surface-container-lowest);
  border-bottom: var(--border-hairline) solid rgba(87, 66, 62, 0.15);
}

.bridge-month-grid__dow span {
  padding: 0.6rem;
  font-size: 0.5625rem;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  text-align: left;
}

.bridge-month-grid__cells {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background-color: rgba(87, 66, 62, 0.18);
  gap: var(--border-hairline);
}
</style>
