import type {
  AccessibilityTag,
  CalendarEvent,
  CalendarEventType,
} from '~/types/calendar-event'

/**
 * Six toggleable filter chips for the Bridge Console "What's On" calendar.
 * The chip set is the union of two backend axes (event type + accessibility
 * tags). Rentals (`private_screening_blackout`) are always shown — they have
 * a distinct visual treatment and are not part of the toggle set.
 */
export const ALL_CHIPS = [
  'showtime',
  'special',
  'member',
  'sensory',
  'captions',
  'audio',
] as const

export type ChipSlug = (typeof ALL_CHIPS)[number]

/**
 * Color tokens used by the chip dots, day-cell event-line accents, and the
 * detail rail's type pip. Lifted from the design handoff's `FC_TYPES` map,
 * with one deviation: `member` substitutes a darker rose (`#D6928A`) for the
 * handoff's `#FFB4A8`, because `#FFB4A8` is the project's `--primary` token
 * and the design system reserves it strictly for text/icon use on
 * `--primary-container` fills (`docs/design-system/DESIGN_SYSTEM.md` §
 * "Token Mapping" — never as a fill, even at small sizes). The substitute
 * keeps the warm "premium / loyalty" reading without violating the rule.
 *
 * Rental is included because it appears in the legend and event lines, even
 * though its chip is not user-toggleable.
 */
export const FC_TYPE_COLORS: Record<ChipSlug | 'rental', string> = {
  showtime: '#CCC6B6',
  special: '#DAC769',
  member: '#D6928A',
  sensory: '#A8C9FF',
  captions: '#A8FFC9',
  audio: '#FFC9A8',
  rental: '#57423E',
}

const TYPE_TO_CHIP: Record<CalendarEventType, ChipSlug | null> = {
  showtime: 'showtime',
  special_event: 'special',
  loyalty_exclusive: 'member',
  // Rentals are always visible — no chip toggle controls them.
  private_screening_blackout: null,
}

const ACCESSIBILITY_TO_CHIP: Record<AccessibilityTag, ChipSlug> = {
  sensory_friendly: 'sensory',
  open_caption: 'captions',
  audio_described: 'audio',
}

const CHIP_SET = new Set<ChipSlug>(ALL_CHIPS)

function isChipSlug(value: string): value is ChipSlug {
  return CHIP_SET.has(value as ChipSlug)
}

export function useBridgeFilters() {
  const activeChips = useState<Set<ChipSlug>>(
    'whats-on:active-chips',
    () => new Set(ALL_CHIPS),
  )

  function setChips(next: Iterable<ChipSlug>): void {
    activeChips.value = new Set(next)
  }

  function toggleChip(slug: ChipSlug): void {
    const next = new Set(activeChips.value)
    if (next.has(slug)) {
      next.delete(slug)
    } else {
      next.add(slug)
    }
    activeChips.value = next
  }

  function isActive(slug: ChipSlug): boolean {
    return activeChips.value.has(slug)
  }

  /**
   * Decides whether a calendar event should be visible given the active chip
   * set. An event is visible if any of its "tags" — its event type OR any of
   * its accessibility tags — maps to an active chip. Rentals always pass.
   */
  function isVisible(event: CalendarEvent): boolean {
    if (event.type === 'private_screening_blackout') return true

    const typeChip = TYPE_TO_CHIP[event.type]
    if (typeChip && activeChips.value.has(typeChip)) return true

    for (const tag of event.accessibilityTags ?? []) {
      const tagChip = ACCESSIBILITY_TO_CHIP[tag]
      if (tagChip && activeChips.value.has(tagChip)) return true
    }

    return false
  }

  /**
   * Serialize the active chip set to the URL query value, or undefined when
   * the set is the default (all six on) so the URL stays clean.
   */
  function toQueryValue(): string | undefined {
    if (activeChips.value.size === ALL_CHIPS.length) return undefined
    return ALL_CHIPS.filter((c) => activeChips.value.has(c)).join(',')
  }

  /**
   * Read a `?chips=` URL value. `undefined` resets to the default (all on).
   * Empty string deactivates everything (rental remains visible).
   */
  function fromQueryValue(value: string | string[] | undefined | null): void {
    if (value === undefined || value === null) {
      activeChips.value = new Set(ALL_CHIPS)
      return
    }
    const raw = Array.isArray(value) ? value.join(',') : value
    if (raw === '') {
      activeChips.value = new Set()
      return
    }
    const parts = raw.split(',').map((s) => s.trim()).filter(Boolean)
    activeChips.value = new Set(parts.filter(isChipSlug))
  }

  /**
   * Translate a legacy `?type=` / `?accessibility=` URL into the chip set so
   * old bookmarks keep working. When neither legacy param is present, do
   * nothing — the caller already initialized via `fromQueryValue`.
   */
  function fromLegacyQuery(
    legacyType: string | string[] | undefined | null,
    legacyAccessibility: string | string[] | undefined | null,
  ): boolean {
    const typeRaw = Array.isArray(legacyType) ? legacyType.join(',') : (legacyType ?? '')
    const accRaw = Array.isArray(legacyAccessibility)
      ? legacyAccessibility.join(',')
      : (legacyAccessibility ?? '')

    if (!typeRaw && !accRaw) return false

    const chips = new Set<ChipSlug>()

    for (const part of typeRaw.split(',').map((s) => s.trim()).filter(Boolean)) {
      const chip = TYPE_TO_CHIP[part as CalendarEventType]
      if (chip) chips.add(chip)
    }
    for (const part of accRaw.split(',').map((s) => s.trim()).filter(Boolean)) {
      const chip = ACCESSIBILITY_TO_CHIP[part as AccessibilityTag]
      if (chip) chips.add(chip)
    }

    activeChips.value = chips.size === 0 ? new Set(ALL_CHIPS) : chips
    return true
  }

  return {
    activeChips: readonly(activeChips),
    setChips,
    toggleChip,
    isActive,
    isVisible,
    toQueryValue,
    fromQueryValue,
    fromLegacyQuery,
  }
}

/**
 * Pick the day's hero film. Tie-breaker: prefer specials and member
 * exclusives, then any non-rental event.
 */
export function pickHeroEvent(events: CalendarEvent[]): CalendarEvent | null {
  if (!events.length) return null
  const featured = events.find(
    (e) => e.type === 'special_event' || e.type === 'loyalty_exclusive',
  )
  if (featured) return featured
  return events.find((e) => e.type !== 'private_screening_blackout') ?? null
}

const TYPE_TO_CHIP_OR_RENTAL: Record<CalendarEventType, ChipSlug | 'rental'> = {
  showtime: 'showtime',
  special_event: 'special',
  loyalty_exclusive: 'member',
  private_screening_blackout: 'rental',
}

/**
 * Resolve a calendar event to the FC type slug used by the chip color map,
 * day-cell flag dots, and event-line accents. Rentals fall through to the
 * dedicated `rental` slug because they're rendered (always visible, distinct
 * treatment) but not part of the toggleable chip set.
 */
export function chipSlugForEvent(event: CalendarEvent): ChipSlug | 'rental' {
  return TYPE_TO_CHIP_OR_RENTAL[event.type]
}

/** Display label per event type, used by the "Also Today" list and any
 *  surface that needs to caption an event by category. */
export const EVENT_TYPE_LABELS: Record<CalendarEventType, string> = {
  showtime: 'Showtime',
  special_event: 'Special',
  loyalty_exclusive: 'Members',
  private_screening_blackout: 'Private rental',
}
