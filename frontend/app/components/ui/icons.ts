/**
 * Inline SVG icon path data.
 * All icons use viewBox="0 0 24 24" and fill="currentColor".
 * Paths sourced from Material Design Icons (Apache 2.0).
 */
export const iconPaths = {
  close:
    'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
  check:
    'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
  'chevron-down':
    'M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z',
  'chevron-up':
    'M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z',
  'chevron-right':
    'M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z',
  'chevron-left':
    'M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z',
  alert:
    'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
  info:
    'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z',
  spinner:
    'M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z',
} as const

export type IconName = keyof typeof iconPaths
