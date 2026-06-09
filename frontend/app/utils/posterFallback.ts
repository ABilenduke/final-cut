/**
 * Branded image-fallback helpers — shared by every surface that renders a
 * remote image which may be missing or 404 (event banners, featured slides,
 * the home retrospective). When an `<img>` fails to load (or is null), those
 * surfaces fall back to a deterministic hashed-hue gradient plus a glyph
 * derived from the title, so a missing binary degrades to an intentional tile
 * instead of a broken-image icon.
 *
 * Lifted verbatim from `BridgeMiniPoster.vue` so the calendar mini-poster and
 * these surfaces share one source of truth.
 */

/** Stable string hash mapped to a 0–359 hue, for the gradient fallback. */
export function hashToHue(input: string): number {
  let h = 0
  for (let i = 0; i < input.length; i += 1) {
    h = (h * 31 + input.charCodeAt(i)) % 360
  }
  return h
}

/** First letters of up to three words, uppercased — e.g. "Kubrick in the grain" → "KIT". */
export function initialsFrom(title: string): string {
  return title
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 3)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('')
}
