<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarEvent } from '~/types/calendar-event'
import { pickHeroEvent } from '~/composables/useBridgeFilters'

const props = defineProps<{
  selectedDate: string
  events: CalendarEvent[]
}>()

const heroEvent = computed<CalendarEvent | null>(() => pickHeroEvent(props.events))

const otherEvents = computed<CalendarEvent[]>(() => {
  if (!heroEvent.value) return props.events
  return props.events.filter((e) => {
    if (e.id === heroEvent.value!.id) return false
    if (
      heroEvent.value!.movieSlug
      && e.movieSlug
      && e.movieSlug === heroEvent.value!.movieSlug
      && e.type === heroEvent.value!.type
    ) {
      return false
    }
    return true
  })
})
</script>

<template>
  <BridgeDetailHero
    :selected-date="selectedDate"
    :events="events"
    :hero-event="heroEvent"
  />

  <BridgeAlsoToday
    v-if="otherEvents.length"
    :events="otherEvents"
  />

  <BridgeCinemaReadout />
</template>
