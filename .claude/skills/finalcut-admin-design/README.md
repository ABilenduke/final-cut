# Final Cut Admin Design System

Design reference for the Filament 5 admin panel. The admin panel inherits
the Cinematic Void Framework palette from the customer site and adapts it
for dense operational work.

---

## Creative Direction

### Calm under load

Operations staff use this panel during busy showtimes. A sold-out Saturday
night means eight things happening at once: a booking dispute, a gift card
inquiry, a showtime cancellation, a loyalty adjustment. The interface earns
trust by staying legible under that pressure. Information is dense but
never chaotic. Actions are close to the data that motivates them. Nothing
blinks, pulses, or demands attention it has not earned.

### Cinematic restraint

The customer site is an experience. The admin panel is a tool. The same
palette applies -- deep maroon, gold, warm-dark surfaces, Noto Serif for
display, Newsreader for body -- but the expression is quieter. Hero gradients
become table headers. Vignette blooms become subtle row accents. The
atmosphere is present in the materials, not the choreography.

### Projection booth at dusk

The mental model is a projection booth at the end of a long Saturday: dim
overhead light, controls exactly where they should be, every indicator
readable at a glance. Nothing decorative that does not also inform. The
operator knows the system, and the system respects that knowledge by staying
out of the way.

---

## Visual Foundations

### Color

#### Shared customer palette

The admin panel reuses the customer site's surface ramp and accent tokens
verbatim. No new neutral surfaces.

| Token | Hex | Role |
|-------|-----|------|
| `--surface` | `#131313` | Page background |
| `--surface-container-lowest` | `#0e0e0e` | Recessed panels, deep voids |
| `--surface-container-low` | `#1c1b1b` | Base card layer |
| `--surface-container` | `#201f1f` | Content sections, sidebar |
| `--surface-container-high` | `#2a2a2a` | Elevated cards, modals, table header |
| `--surface-variant` | `#3B3636` | Glassmorphism substrate |
| `--primary-container` | `#550000` | Maroon fill: primary button, active badge, accent row |
| `--primary` | `#FFB4A8` | Salmon text on maroon fills ONLY |
| `--secondary` | `#DAC769` | Gold: CTA text, active nav indicator, focused input |
| `--secondary-container` | `#675900` | Secondary accent fills (rare) |
| `--tertiary` | `#CCC6B6` | Ivory body text, subdued labels |
| `--on-surface` | `#E5E2E1` | Default readable text on dark surfaces |
| `--outline` | `#A58B86` | Input underlines, functional borders |
| `--outline-variant` | `#57423E` | Decorative edge catches at 15% opacity only |

The salmon-vs-maroon rule from the customer design system applies without
exception in the admin panel. See `docs/design-system/DESIGN_SYSTEM.md`
section "CRITICAL: Token Mapping" for the full rule.

#### Admin-only semantic accents

Four semantic accents extend the palette for operational status communication.
These do not exist in the customer site.

| Role | Hex | Use |
|------|-----|-----|
| Success / confirmed | `#5b8f6c` | Confirmed bookings, published state, success toasts |
| Destructive / danger | `#b5443d` | Void, cancel, delete, error states |
| Warning / caution | `#c78438` | Refund pending, unresolved flags, elevated actions |
| Info / held | `#5a8aa0` | Held seats, info banners, neutral metadata |

Use these as tints at 15-20% opacity for row backgrounds and as solid fills
for status pill backgrounds. Do not use full-opacity fills on large surfaces.

### Typography

Typefaces are shared with the customer site. No new fonts are introduced.

**Noto Serif** for display contexts: page titles, section headings, modal
headers. Set at `headline-sm` (1.5rem) or above. Letter-spacing: -0.02em.

**Newsreader** for everything else: table cell text, form labels, body copy,
button labels, status pill text, filter chip labels. The Filament panel wires
Newsreader via `->font('Newsreader', provider: GoogleFontProvider::class)` in
AdminPanelProvider.

**Table headers** use Newsreader set in ALL CAPS with positive tracking
(0.06em). This is one of the three permitted ALL CAPS uses in the admin
(the others: status chip text, eyebrow labels above a stat card).

**Tabular figures** are required for any column where values will be
compared: booking totals, seat counts, loyalty points, timestamps. Apply
`font-variant-numeric: tabular-nums` via the `admin-tabular` utility class
defined in `theme.css`.

### Spacing

The admin panel uses the same 4px base grid as the customer site, expressed
through the same token scale (`--space-2xs` through `--space-5xl`). Filament's
own internal spacing is left untouched; tokens apply to custom Blade views,
custom panels, and any CSS written for this project.

Dense data tables use `--space-sm` (0.5rem) for cell padding. Standard form
sections use `--space-md` (1rem). Section gaps use `--space-xl` (2rem).

### Radii

The `sm` radius (0.125rem) is the default for all admin components: status
pills, badges, table action buttons, modal dialogs, form inputs.

No `rounded-full` or `rounded-xl`. The admin panel inherits the same
anti-softness rule as the customer site. Sharp edges signal precision.

### Shadows

Shadows appear only on floating elements: modals, dropdown panels, tooltip
bubbles. Static surfaces -- cards, table rows, sidebar panels -- are
distinguished by surface tier, never by shadow.

Floating element shadow: `box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.5)`.

### Borders

No divider lines between sections or table rows. Depth is communicated by
surface tier shift alone. The one exception: the horizontal rule between the
table toolbar and the table body uses `--outline-variant` at 15% opacity.

Functional interactive boundaries (input fields, toggle controls) use
`--outline` (#A58B86) at full opacity per the customer design system's
interactive identification rule.

### Motion

Short, flat, no bounces. Admin transitions run at `--duration-micro` (100ms)
for hover and active states, `--duration-standard` (250ms) for dropdowns and
accordions. No `--duration-cinematic` in the admin panel. Reduced motion:
all transitions cut to instant.

---

## Content Fundamentals

### Voice

Calm, literate, hospitality-veteran. The copy speaks the way a head usher
would: knowledgeable, efficient, never condescending. It assumes the reader
knows what a booking confirmation code is. It does not over-explain.

### Tone

Imperative but polite. Actions are named as verbs: "Publish showtime", "Void
booking", "Adjust points". Not "Submit" or "Confirm action". The label
tells the operator exactly what will happen.

Warnings and confirmations are matter-of-fact. No exclamation marks. No
"Are you sure?" -- instead: "Void this booking. The seat will be released and
the customer notified. This cannot be undone."

### Casing

| Context | Case |
|---------|------|
| Page titles | Sentence case |
| Section headings | Sentence case |
| Button labels | Sentence case |
| Navigation items | Sentence case |
| Table headers | ALL CAPS + tracking |
| Status chip text | ALL CAPS |
| Eyebrow labels | ALL CAPS |
| All other text | Sentence case |

Never title-case a sentence. Never ALL CAPS a paragraph.

### Numbers

**Time:** 24-hour always. "19:30" not "7:30 PM". "09:05" not "9:05".
Include the date when the value might span midnight: "Sat 25 Apr, 19:30".

**Currency:** Two decimal places always. Thousands separator for values
above 999. "$18,420.00" not "$18420". "$0.00" not "$0" or blank.

**Counts:** Thousands separator at or above 1,000. "1,204 seats" not
"1204 seats".

**Digits:** Use tabular figures (`font-variant-numeric: tabular-nums`) in
any column where values will be compared. Apply the `admin-tabular` utility
class.

**Uncertain values:** Use an em dash (--) not zero or "N/A". "Balance: --"
is honest; "Balance: 0" may be read as a confirmed zero.

### Emoji

Never, anywhere. Heroicons for iconography (outline style, 1.6 stroke weight).
Lucide for any icons not available in Heroicons. Unicode dingbats (arrows,
separators) are acceptable only in body copy or audit log entries, never in
headings, labels, or status chips.

### Worked copy examples

These illustrate the voice and formatting conventions:

"Eight showtimes sold out this weekend."

"Refund processed to original card ending 4242."

"Held seats auto-release after 7 minutes of inactivity."

"Amount exceeds $200.00. Ask a supervisor to sign off before continuing."

"Gift card float running low. Lobby 2 has 4 cards remaining."

"Refund issued by A. Okafor at 19:42."

"No bookings match CVF-A3X9K2. Check the code and try again."

"Showtime cancelled. 34 customers will be notified by email."

### Date and time format

Full date: "Sat 25 Apr 2026". Short date (within the current year): "25 Apr".
Time: "19:30". Combined: "Sat 25 Apr, 19:30". Range: "25 Apr -- 02 May".

Timestamps in audit logs: ISO-like with 24h time and no timezone abbreviation
unless displaying a non-local timezone: "2026-04-25 19:42:07".

### Audit log past tense

Audit log entries use past tense, third person. Actor name precedes the
action:

"A. Okafor voided booking CVF-A3X9K2."
"System released held seats for showtime #4481."
"M. Patel adjusted loyalty points: +200 (manual correction)."

---

## Iconography

Filament ships Heroicons as its default icon set. Use Heroicons for all
standard admin actions: edit (pencil), delete (trash), view (eye), search
(magnifying glass), filter (funnel), close (x-mark), check (check), warn
(exclamation-triangle).

Use Lucide icons for domain-specific concepts not well-represented in
Heroicons: seat layout, film reel, ticket stub, calendar schedule.

Rules:
- Outline style always. 1.6 stroke weight. Filled variants only for
  confirmed / terminal states in status pills where fill communicates
  finality.
- Never use emoji as icons. Never use Unicode dingbats as icons.
- Icon-only buttons require `aria-label` or a visually-hidden text label.
- Pair icons with text in all primary actions. Icon-only is acceptable
  only in toolbar secondary actions (edit / delete rows) where the context
  is unambiguous.
