import type { Showtime } from '~/types/showtime'

/** One showtime chip in the home hero side panel. */
export interface HeroSlot {
  id: string
  locationSlug: string
  time: string // e.g. "7:30"
  meridiem: string // "AM" | "PM"
}

const MAX_SLOTS = 8

/**
 * Map a movie's upcoming cross-location showtimes to hero panel chips
 * (admin-v2 Plan 16 — replaces the hardcoded placeholder slots). Times are
 * formatted in the app display timezone; input order (the API sorts by
 * start_time) is preserved and capped at eight. Showtimes without a
 * location payload are dropped — the purchase URL contract requires a
 * non-empty `loc` slug.
 */
export function buildHeroSlots(showtimes: Showtime[], timeZone: string): HeroSlot[] {
  const formatter = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
    timeZone,
  })

  return showtimes
    .filter((showtime) => showtime.location?.slug)
    .slice(0, MAX_SLOTS)
    .map((showtime) => {
      const parts = formatter.formatToParts(new Date(showtime.startTime))
      const get = (type: string) => parts.find((p) => p.type === type)?.value ?? ''

      return {
        id: showtime.id,
        locationSlug: showtime.location!.slug,
        time: `${get('hour')}:${get('minute')}`,
        meridiem: get('dayPeriod').toUpperCase(),
      }
    })
}
