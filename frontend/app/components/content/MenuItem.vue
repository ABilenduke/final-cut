<script setup lang="ts">
import type { MenuItem } from '~/types/menu-item'

/**
 * Public browse-mode menu item card.
 *
 * Renders name, description, price, dietary badges, and an optional
 * per-item availability caption when the item is not stocked at every venue.
 *
 * Caption rules (Task 7):
 *   - available_at.length === venues.length → no caption (item is everywhere)
 *   - available_at is a strict subset     → "Available at X only" (one venue)
 *                                            "Available at X · Y" (multiple, but not all)
 *   - available_at is [] or undefined     → treated as available everywhere (no caption)
 *     (a truly unavailable item should be filtered upstream before this component)
 *
 * Venue names are derived by matching available_at slugs against the passed
 * `venues` array. Unknown slugs are skipped. If venues is empty or not
 * provided the caption is suppressed (graceful degradation — the SSR render
 * still shows the item, just without the caption).
 */

const props = defineProps<{
  item: MenuItem
  /** Full venue roster from usePublicLocations — needed to compute captions. */
  venues?: Array<{ slug: string; name: string }>
}>()

const availabilityCaption = computed<string | null>(() => {
  const venues = props.venues ?? []
  const availableAt = props.item.available_at ?? []

  // No venue roster available, or item carries no availability data → no caption.
  if (venues.length === 0 || availableAt.length === 0) return null

  // Item available at every venue → no caption needed.
  if (availableAt.length >= venues.length) return null

  // Resolve venue names for the slugs that carry this item.
  const names = availableAt
    .map(slug => venues.find(v => v.slug === slug)?.name)
    .filter((n): n is string => Boolean(n))

  if (names.length === 0) return null

  if (names.length === 1) {
    return `Available at ${names[0]} only`
  }

  return `Available at ${names.join(' · ')}`
})

const DIETARY_LABELS: Record<string, string> = {
  vegan: 'Vegan',
  vegetarian: 'V',
  gluten_free: 'GF',
}

const dietaryMarks = computed(() =>
  props.item.dietary.map(tag => ({
    key: tag,
    label: DIETARY_LABELS[tag] ?? tag.toUpperCase(),
  })),
)
</script>

<template>
  <article class="menu-item">
    <div class="menu-item__body">
      <div class="menu-item__head">
        <h3 class="menu-item__name">{{ item.name }}</h3>
        <span class="menu-item__price">{{ formatCurrency(item.price) }}</span>
      </div>

      <p class="menu-item__description">{{ item.description }}</p>

      <div v-if="dietaryMarks.length > 0" class="menu-item__dietary" aria-label="Dietary information">
        <span
          v-for="d in dietaryMarks"
          :key="d.key"
          class="menu-item__diet-tag"
          :class="{ 'menu-item__diet-tag--gf': d.key === 'gluten_free' }"
        >
          {{ d.label }}
        </span>
      </div>
    </div>

    <!--
      Availability caption — FTC/UX: make it clear when an item is only at
      some venues so users aren't disappointed at the box office.
      Uses on-tertiary-fixed-variant (low-emphasis telemetry text, 7.11:1 on
      surface) rather than state-warning gold, to keep the catalog scannable
      without every partial item screaming for attention. Gold is reserved for
      actual errors and warnings with user-action implications.
    -->
    <p v-if="availabilityCaption" class="menu-item__availability" aria-label="Availability note">
      {{ availabilityCaption }}
    </p>
  </article>
</template>

<style scoped>
.menu-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border-radius: var(--radius-card);
}

.menu-item__body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.menu-item__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--space-sm);
}

.menu-item__name {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.15;
  color: var(--on-surface);
  text-wrap: pretty;
  margin: 0;
}

.menu-item__price {
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: -0.01em;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.menu-item__description {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.5;
  font-style: italic;
  margin: 0;
}

.menu-item__dietary {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-top: 0.1rem;
}

.menu-item__diet-tag {
  font-family: var(--font-body);
  font-size: 0.5625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding: 0.2rem 0.4rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
  border-radius: var(--radius-sm);
}

.menu-item__diet-tag--gf {
  color: var(--secondary);
  border-color: rgb(var(--secondary-rgb) / 0.35);
}

/*
 * Availability caption — low-emphasis ambient text (on-tertiary-fixed-variant,
 * #A89F91, 7.11:1 on surface). A small leading glyph cues the note type
 * without a warning-colored badge that would over-signal a non-blocking note.
 */
.menu-item__availability {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.08em;
  color: var(--on-tertiary-fixed-variant);
  margin: 0;
  padding-top: var(--space-xs);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.menu-item__availability::before {
  content: '→ ';
  color: var(--secondary);
  font-family: var(--font-display);
}
</style>
