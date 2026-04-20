/**
 * Editorial pairing curated to a specific film. Three courses (popcorn / bar / sweet)
 * sold as a discounted bundle — the "Reel 02" centerpiece on the snacks step.
 *
 * Frontend-only data for now (see app/data/pairings.ts). If these prove their weight,
 * promote to a backend `programme_pairings` table keyed by movie + location.
 */
export interface ProgrammePairing {
  id: string
  /** Movie slug this pairing belongs to. Matches Showtime.movieSlug. */
  movieSlug: string
  /** Display number — e.g. "No. 17". */
  number: string
  /** Top-line tag — e.g. "Programme Pairing No. 17 · Tonight only". */
  tag: string
  /** Editorial title — supports a leading italic accent via `<em>` in the display. */
  title: string
  /** Italic accent fragment of the title (e.g. "Arrakis"). Stored separately so the
   *  card can render it in the tertiary tone. */
  titleAccent: string
  /** Suffix after the accent — e.g. "flight." */
  titleTail: string
  /** Curator note paragraph. */
  curatorNote: string
  /** Three courses, in order. Prices in cents. `menuItemId` is optional; the pairing
   *  exists as its own line item, but linking ids lets us highlight matching catalog
   *  rows ("Tonight" badge) without duplicating the data. */
  courses: Array<{
    id: string
    name: string
    price: number
    menuItemId?: string
  }>
  /** Bundle price in cents. Saving = sum(courses.price) - bundlePrice. */
  bundlePrice: number
  /** Curator byline — e.g. "Nadia Obéron". */
  curatorName: string
  /** Curator role — e.g. "Head of Programming". */
  curatorRole: string
  /** Validity blurb shown alongside the price — e.g. "Save $5 tonight". */
  validUntil?: string
  /** CSS radial-gradient string for the poster art. Falls back to the house default. */
  gradient?: string
  /** Single-character glyph overlaid on the poster art (italic). */
  glyph: string
}
