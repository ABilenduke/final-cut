<script setup lang="ts">
import type { MenuItem } from '~/types/menu-item'

const props = withDefaults(defineProps<{
  item: MenuItem
  /** How many of this item are in the cart. Always 0 in browse-only mode. */
  quantity?: number
  /** When false, hides the Add / qty controls — the card becomes a static menu listing. */
  interactive?: boolean
  /**
   * Full venue roster used to compute the availability caption.
   * Provided by the /food-drink browse page from usePublicLocations.
   * Omitted in the booking checkout flow (where the menu is already
   * filtered to the booking's location and captions are replaced by the
   * dimming/badge treatment in Task 9).
   */
  venues?: Array<{ slug: string; name: string }>
}>(), {
  quantity: 0,
  interactive: true,
  venues: () => [],
})

const emit = defineEmits<{
  add: [itemId: string]
  increment: [itemId: string]
  decrement: [itemId: string]
}>()

const CATEGORY_GRADIENTS: Record<string, string> = {
  popcorn: 'radial-gradient(ellipse at 50% 40%, #d4a868 0%, #8a5a2a 55%, #2a1808 100%)',
  drinks: 'radial-gradient(ellipse at 40% 45%, #8a2020 0%, #3a0a0a 55%, #180404 100%)',
  snacks: 'radial-gradient(ellipse at 50% 50%, #a07840 0%, #502810 55%, #180a04 100%)',
  combos: 'radial-gradient(ellipse at 40% 40%, #c88a30 0%, #6a4010 55%, #1a0a04 100%)',
  specials: 'radial-gradient(ellipse at 50% 40%, #6a4020 0%, #2a1808 55%, #0a0402 100%)',
}

const CATEGORY_LABELS: Record<string, string> = {
  popcorn: 'Popcorn & snacks',
  drinks: 'Bar',
  snacks: 'Popcorn & snacks',
  combos: 'Pairings',
  specials: 'Sweets',
}

const DIETARY_LABELS: Record<string, string> = {
  vegan: 'V',
  vegetarian: 'V',
  gluten_free: 'GF',
}

const gradient = computed<string>(() =>
  props.item.gradient
  ?? CATEGORY_GRADIENTS[props.item.category]
  ?? CATEGORY_GRADIENTS.snacks!,
)

const glyph = computed<string>(() => {
  if (props.item.glyph) return props.item.glyph
  const first = props.item.name.trim().charAt(0).toUpperCase()
  return first || '·'
})

// A small subset of menu items (negroni, olives, chocolate, madeleine,
// caramel) have no concession photo yet — their imageUrl is the empty
// string. Those fall back to the gradient + glyph thumbnail. We also flip
// to the fallback if the configured URL fails to load (CDN ACL changes,
// missing object) so a broken-image icon never reaches the user.
const imageFailed = ref(false)
const showImage = computed<boolean>(
  () => Boolean(props.item.imageUrl) && !imageFailed.value,
)

const categoryLabel = computed<string>(() =>
  CATEGORY_LABELS[props.item.category] ?? props.item.category,
)

const curatorLabel = computed<string>(() => props.item.curator ?? 'House')

const dietaryMarks = computed<Array<{ key: string; label: string }>>(() =>
  props.item.dietary.map(tag => ({
    key: tag,
    label: DIETARY_LABELS[tag] ?? tag.toUpperCase(),
  })),
)

/**
 * Per-item availability caption for the cross-location browse page.
 *
 * Rules (Task 7 / PAGE_SPECS.md § /food-drink):
 *   - available_at equals full venue roster → no caption (item everywhere)
 *   - strict subset, one venue            → "Available at X only"
 *   - strict subset, multiple             → "Available at X · Y"
 *   - no venues prop or no available_at   → no caption (graceful degradation)
 */
const availabilityCaption = computed<string | null>(() => {
  const venues = props.venues ?? []
  const availableAt = props.item.available_at ?? []

  if (venues.length === 0 || availableAt.length === 0) return null
  if (availableAt.length >= venues.length) return null

  const names = availableAt
    .map(slug => venues.find(v => v.slug === slug)?.name)
    .filter((n): n is string => Boolean(n))

  if (names.length === 0) return null
  if (names.length === 1) return `Available at ${names[0]} only`
  return `Available at ${names.join(' · ')}`
})
</script>

<template>
  <article
    class="prod"
    :class="{ 'prod--on': props.quantity > 0, 'prod--browse': !props.interactive }"
  >
    <div
      class="prod__thumb"
      :class="{ 'prod__thumb--has-image': showImage }"
      :style="{ background: gradient }"
      aria-hidden="true"
    >
      <img
        v-if="showImage"
        :src="props.item.imageUrl"
        alt=""
        class="prod__img"
        loading="lazy"
        decoding="async"
        @error="imageFailed = true"
      >
      <span class="prod__cat-tag">{{ categoryLabel }}</span>
      <span v-if="props.item.flag" class="prod__flag">{{ props.item.flag }}</span>
      <span v-if="!showImage" class="prod__glyph">{{ glyph }}</span>
    </div>

    <div class="prod__body">
      <div class="prod__head">
        <h3 class="prod__name">{{ props.item.name }}</h3>
        <span class="prod__pr">{{ formatCurrency(props.item.price) }}</span>
      </div>

      <p class="prod__dx">{{ props.item.description }}</p>

      <div class="prod__meta">
        <span v-if="props.item.size" class="prod__diet prod__diet--size">{{ props.item.size }}</span>
        <span
          v-for="d in dietaryMarks"
          :key="d.key"
          class="prod__diet"
          :class="{ 'prod__diet--gf': d.key === 'gluten_free' }"
        >
          {{ d.label }}
        </span>
      </div>
    </div>

    <!-- Availability caption — shown only on browse page when venues prop is provided -->
    <p v-if="availabilityCaption" class="prod__availability" aria-label="Availability note">
      {{ availabilityCaption }}
    </p>

    <div class="prod__foot">
      <span class="prod__curator"><b>{{ curatorLabel }}</b> · curated</span>

      <template v-if="props.interactive">
        <div
          v-if="props.quantity > 0"
          class="prod__qty"
          role="group"
          :aria-label="`${props.item.name} quantity`"
        >
          <button
            type="button"
            class="prod__qty-btn"
            :aria-label="`Remove one ${props.item.name}`"
            @click="emit('decrement', props.item.id)"
          >
            −
          </button>
          <span class="prod__qty-n" aria-live="polite">{{ props.quantity }}</span>
          <button
            type="button"
            class="prod__qty-btn"
            :aria-label="`Add another ${props.item.name}`"
            @click="emit('increment', props.item.id)"
          >
            +
          </button>
        </div>
        <button
          v-else
          type="button"
          class="prod__add"
          :aria-label="`Add ${props.item.name} to your tray`"
          @click="emit('add', props.item.id)"
        >
          <span class="prod__add-plus" aria-hidden="true">+</span>
          Add
        </button>
      </template>
    </div>
  </article>
</template>

<style scoped>
.prod {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: var(--space-md);
  background-color: var(--surface-container-low);
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
  border-radius: var(--radius-card);
  transition: border-color var(--duration-standard) var(--ease-standard);
  text-align: left;
}

.prod:hover {
  border-color: rgb(var(--secondary-rgb) / 0.35);
}

.prod--on {
  border-color: var(--secondary);
  background-color: rgb(var(--secondary-rgb) / 0.04);
}

.prod__thumb {
  aspect-ratio: 4 / 3;
  border-radius: var(--radius-sm);
  position: relative;
  overflow: hidden;
}

.prod__thumb::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    0deg,
    transparent 0 0.125rem,
    rgba(0, 0, 0, 0.08) 0.125rem 0.1875rem
  );
  mix-blend-mode: multiply;
  /* Above the photo so the scanline still reads, below the cat-tag /
     flag overlays which sit at z-index 2 by default in stacking order. */
  z-index: 1;
}

/* Suppress the scanline overlay when a real photo is showing — the texture
   was a stand-in for missing imagery and only muddies the actual webp. */
.prod__thumb--has-image::before {
  display: none;
}

.prod__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  /* Below the cat-tag / flag overlays (DOM-order natural stacking) and
     below the scanline ::before — but the scanline is hidden when this
     element is present, so the photo reads cleanly. */
  z-index: 0;
}

.prod__glyph {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-family: var(--font-display);
  font-style: italic;
  font-size: 3.5rem;
  color: rgba(255, 255, 255, 0.14);
  letter-spacing: -0.05em;
}

.prod__cat-tag {
  position: absolute;
  top: 0.55rem;
  left: 0.55rem;
  font-family: var(--font-body);
  font-size: 0.5625rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.7);
  background-color: rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  padding: 0.25rem 0.45rem;
  border-radius: var(--radius-sm);
}

.prod__flag {
  position: absolute;
  top: 0.55rem;
  right: 0.55rem;
  font-family: var(--font-display);
  font-size: 0.6875rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--secondary);
  background-color: rgba(14, 14, 14, 0.75);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  border: var(--border-hairline) solid rgb(var(--secondary-rgb) / 0.3);
}

.prod__body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.prod__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--space-sm);
}

.prod__name {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: -0.01em;
  line-height: 1.15;
  color: var(--on-surface);
  text-wrap: pretty;
  margin: 0;
}

.prod__pr {
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--secondary);
  font-weight: 500;
  letter-spacing: -0.01em;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.prod__dx {
  font-family: var(--font-body);
  font-size: 0.8125rem;
  color: var(--tertiary);
  line-height: 1.5;
  font-style: italic;
  margin: 0;
}

.prod__meta {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
  margin-top: 0.1rem;
}

.prod__diet {
  font-family: var(--font-body);
  font-size: 0.5625rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
  padding: 0.2rem 0.4rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.4);
  border-radius: var(--radius-sm);
}

.prod__diet--size {
  color: var(--tertiary);
  border-color: rgb(var(--outline-variant-rgb) / 0.5);
}

.prod__diet--gf {
  color: var(--secondary);
  border-color: rgb(var(--secondary-rgb) / 0.35);
}

.prod__foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 0.6rem;
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.25);
  margin-top: auto;
}

.prod--browse .prod__foot {
  border-top: none;
  padding-top: 0;
}

.prod__curator {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--on-tertiary-fixed-variant);
}

.prod__curator b {
  color: var(--tertiary);
  font-family: var(--font-display);
  font-weight: 500;
  letter-spacing: 0.04em;
}

.prod__qty {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
  font-size: 1rem;
  color: var(--on-surface);
}

.prod__qty-btn {
  width: 1.75rem;
  height: 1.75rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.5);
  border-radius: var(--radius-sm);
  display: grid;
  place-items: center;
  color: var(--on-surface);
  background: none;
  cursor: pointer;
  font-family: var(--font-display);
  font-size: 1rem;
  line-height: 1;
  transition:
    border-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard);
}

.prod__qty-btn:hover,
.prod__qty-btn:focus-visible {
  border-color: var(--secondary);
  color: var(--secondary);
}

.prod__qty-n {
  min-width: 1.25rem;
  text-align: center;
}

.prod__add {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  border: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.5);
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--on-surface);
  transition:
    border-color var(--duration-standard) var(--ease-standard),
    color var(--duration-standard) var(--ease-standard);
  background-color: var(--surface-container);
  cursor: pointer;
}

.prod__add:hover,
.prod__add:focus-visible {
  border-color: var(--secondary);
  color: var(--secondary);
}

.prod__add-plus {
  color: var(--secondary);
  font-family: var(--font-display);
  font-size: 0.9rem;
  line-height: 1;
}

/*
 * Availability caption — low-emphasis ambient text per the design system's
 * on-tertiary-fixed-variant token (#A89F91, 7.11:1 on surface). A subtle
 * separator and leading arrow glyph signal "note" without a warning-colored
 * badge that would over-index on a non-blocking informational cue.
 */
.prod__availability {
  font-family: var(--font-body);
  font-size: 0.6875rem;
  letter-spacing: 0.08em;
  color: var(--on-tertiary-fixed-variant);
  margin: 0;
  padding-top: var(--space-xs);
  border-top: var(--border-hairline) solid rgb(var(--outline-variant-rgb) / 0.2);
}

.prod__availability::before {
  content: '→ ';
  color: var(--secondary);
  font-family: var(--font-display);
}

@media (max-width: 60rem) {
  .prod__qty-btn {
    width: 3rem;
    height: 3rem;
  }
}
</style>
