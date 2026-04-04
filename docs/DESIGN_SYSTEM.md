# Design System Document: The Cinematic Void Framework

## 1. Overview & Creative North Star: "The Final Cut"
The Creative North Star for this design system is **The Final Cut**. It is an aesthetic that lives at the intersection of classic cinema and science fiction — the compositional discipline of a Kubrick one-point perspective, the atmospheric tension of Ridley Scott's darkness, and the quiet enormity of a ship moving through empty space.

This is not a "utility-first" interface; it is a directed experience. We break the "template" look by favoring **intentional asymmetry** and **tonal depth** over rigid grids. Elements should feel like they are resolving out of darkness — layered compositions surfacing at different focal depths, guided by high-contrast typography that carries the weight of a title card and the precision of bridge instrumentation. This system rejects the flat, sterile nature of modern SaaS, opting instead for an atmosphere that is at once cinematic and quietly futuristic.

---

## 2. Colors: Shadow & Signal
The palette is built on a foundation of deep, vacuum-black surfaces, punctuated by a reactor-core maroon and metallic accents that read as both analog warmth and distant starlight.

### Color Tokens
*   **Primary (Core Red):** `#ffb4a8` (Diffused for UI legibility) | **Container:** `#550000` (The deep, thermal heart of the system — a warning light behind smoked glass).
*   **Secondary (Signal Gold):** `#dac769` | **Container:** `#675900`.
*   **Tertiary (Hull Ivory):** `#ccc6b6` | **Container:** `#29261b`.
*   **Neutral (Deep Space / Carbon):** `surface: #131313` | `surface_container_lowest: #0e0e0e`.

### The "No-Line" Rule
**Explicit Instruction:** Designers are prohibited from using 1px solid borders to section content. Layout boundaries must be defined solely through background color shifts or negative space. A `surface-container-low` card sitting on a `surface` background provides enough contrast to be felt without being drawn — hull panels distinguished by material grade, not visible seams.

### Surface Hierarchy & Nesting
Treat the UI as a series of focal planes — a deep-focus composition where every layer occupies a deliberate position in z-space. Use the `surface-container` tiers (Lowest to Highest) to create that sense of nested depth.
*   **Background Layer:** `surface` (#131313) — the void behind everything.
*   **Content Sections:** `surface_container` (#201f1f) — the primary deck.
*   **Active Elements/Floating Modals:** `surface_container_high` (#2a2a2a) — the HUD layer, closest to the viewer.

### The "Glass & Gradient" Rule
To simulate the quality of light passing through layered transparency — a viewport, a projection, a heads-up display — use Glassmorphism for floating elements. Apply `surface_variant` at 60% opacity with a `20px` backdrop-blur.
**Signature Texture:** Apply a subtle radial gradient from `primary_container` (#550000) to `surface_container_lowest` (#0e0e0e) in hero sections to produce a "vignette bloom" — the visual equivalent of a reactor core glimpsed through a corridor, or a practical light just outside the frame.

---

### CRITICAL: Token Mapping (Color Token Implementation Rules)

This design system uses Material Design 3 token naming. The `primary` token is NOT the dominant visual color. Misapplying these tokens is the single most common implementation error.

#### The Rule

`primary` (#FFB4A8) = TEXT/ICON color. It is salmon-pink. You will almost never use it as a fill, background, or border.

`primary_container` (#550000) = FILL color. It is deep maroon. This is the color that should dominate buttons, active states, accent backgrounds, and hero elements.

If you are about to apply `primary` (#FFB4A8) as a `background-color`, `fill`, `border-color`, or any visual surface — STOP. You almost certainly want `primary_container` (#550000) instead.

#### Complete Token-to-Role Mapping

```
FILLS / BACKGROUNDS / SURFACES:
  primary_container:  #550000   → Primary buttons, active nav indicators, hero accents
  secondary_container: #675900  → Secondary accent fills (rare)
  tertiary_container:  #29261b  → Subtle warm highlights
  surface:             #131313  → Page background
  surface_container_lowest: #0e0e0e → Recessed/void areas
  surface_container_low:    #1c1b1b → Card backgrounds on surface
  surface_container:         #201f1f → Content sections
  surface_container_high:    #2a2a2a → Elevated cards, modals, active elements

TEXT / ICONS / FOREGROUND:
  primary:       #FFB4A8  → Text ON primary_container fills ONLY
  secondary:     #DAC769  → Gold text for CTAs, tertiary buttons, hover underlines
  tertiary:      #CCC6B6  → Ivory body text, subdued labels
  on_surface:    #E5E2E1  → Default body text on dark backgrounds

BORDERS / EDGES (use sparingly per "No-Line" rule):
  outline_variant: #57423E at 15% opacity → Ghost borders / edge catches only
  outline:         #A58B86 → Input underlines (unfocused state)
```

#### Never Do This

```css
/* WRONG — salmon pink as a background */
.button-primary {
  background-color: #FFB4A8;
  color: #131313;
}

/* WRONG — salmon pink as a nav highlight */
.nav-active {
  border-bottom: 2px solid #FFB4A8;
}

/* WRONG — salmon pink as an icon fill */
.icon-active {
  color: #FFB4A8;
}
```

#### Always Do This

```css
/* CORRECT — deep maroon fill, gold text */
.button-primary {
  background-color: #550000;
  color: #DAC769;
}

/* CORRECT — gold for nav highlights */
.nav-active {
  border-bottom: 2px solid #DAC769;
}

/* CORRECT — gold or ivory for active icons */
.icon-active {
  color: #DAC769;
}
```

#### Where Each Color Appears Visually

- **#550000 (primary_container):** Button fills. Hero vignette gradient origin. Active card accent. The reactor core — use sparingly.
- **#FFB4A8 (primary):** Text sitting directly on #550000 backgrounds, and sparingly for high-emphasis text labels on dark surfaces where maroon fills aren't present.
- **#DAC769 (secondary/gold):** CTA text, hover underlines, active nav indicators, focused input underlines, icon accents.
- **#CCC6B6 (tertiary/ivory):** Body text, subdued metadata, secondary labels.
- **#E5E2E1 (on_surface):** Primary readable text on any dark surface.

#### Surface Stacking (Dark to Light = Back to Front)

```
#0e0e0e  surface_container_lowest  → Deepest void / recessed panels
#131313  surface                   → Page background
#1c1b1b  surface_container_low     → Base card layer
#201f1f  surface_container         → Content sections
#2a2a2a  surface_container_high    → Floating modals, elevated cards
```

Depth is communicated by surface tier, never by drop shadows on static elements.

#### Additional Constraints

- NO `#FFFFFF` anywhere. Max white is `on_surface` (#E5E2E1).
- NO 1px solid borders for layout. Use surface tier shifts only.
- Border radius: `0.125rem` (sm) or `0` (none). Never `full` or `xl`.
- Ghost borders (when accessibility requires): `outline_variant` (#57423E) at 15% opacity only.
- Glassmorphism for floating elements: `surface_variant` at 60% opacity + `backdrop-filter: blur(20px)`.
- Shadows on floating elements only: `box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6)`. Shadow tint must match background, never gray.

---

## 3. Typography: The Director's Voice
We use **Noto Serif** for its structural authority and **Newsreader** for its high-legibility editorial grace. The pairing is deliberate: the gravitas of opening credits meets the functional clarity of on-screen telemetry.

*   **Display (Lg/Md/Sm):** `notoSerif`. Reserve for hero moments. Set with tight letter-spacing (-0.02em) — these should read like the title card of a film you'd watch twice: deliberate, monumental, unhurried.
*   **Headline & Title:** `notoSerif`. These are your **establishing shots** — the information the eye locks onto first. Use these to break the grid: align headlines to the far left while body copy sits in a narrower, offset column.
*   **Body & Label:** `newsreader`. The **mission log**. Newsreader's organic curves provide a human counterpoint to the architectural headlines — the warm voice narrating over cold, beautiful imagery.

The hierarchy is intentionally dramatic. A `display-lg` (3.5rem) may sit directly above a `label-md` (0.75rem) to create a high-contrast editorial scale — the kind of typographic distance you see on a poster for a film that trusts its title to do the work.

---

## 4. Elevation & Depth: Tonal Layering
In this system, light doesn't come from a global source. It emanates from within — the way a control panel glows in a darkened cockpit, the way a corridor light spills across a floor in a Deakins-lit frame.

*   **The Layering Principle:** Avoid shadows for static components. Instead, place a `surface-container-lowest` (#0e0e0e) element inside a `surface-container-low` (#1c1b1b) section. This "recessed" treatment reads as milled depth — a channel cut with precision rather than a shadow cast by accident.
*   **Ambient Shadows:** For floating elements (like a Gold CTA button), use a diffused shadow: `box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6)`. The shadow must never be gray; it should be a darkened tint of the background — an element hovering above its surface, casting its own penumbra.
*   **The "Edge Catch":** If accessibility requires a stroke, use `outline-variant` (#57423e) at **15% opacity**. This creates a faint luminous edge — light grazing a polished surface, a containment field barely visible — rather than a structural border.

---

## 5. Components: The Leading Roles

### Buttons
*   **Primary:** Filled with `primary_container` (#550000). Text in `secondary` (Gold). Shape: `sm` (0.125rem) — sharp, machined, no unnecessary softness. This is a deliberate object.
*   **Tertiary (Text Only):** `secondary` (Gold) text with an animated underline that extends from center on hover — a system coming online.

### Cards & Lists
*   **Rule:** No divider lines.
*   **Implementation:** Use a `1.5rem` vertical spacing shift or a subtle transition from `surface_container` to `surface_container_high` to distinguish items. Active list items may carry a faint vertical gradient on their leading edge — a data-stream indicator showing liveness without breaking the no-divider rule. Card edges should use the `DEFAULT` (0.25rem) radius — just enough to imply fabrication tolerance without losing the angular precision.

### Input Fields
*   **Styling:** Underline-only style using the `outline` token (#a58b86). On focus, the underline transitions to `secondary` (Gold) with a subtle outer glow (Glassmorphism). The field should feel like it activates — a system acknowledging input — rather than simply receiving it.

### Signature Component: The "Neural Ticker"
A horizontally scrolling data feed of `label-sm` text in `on_tertiary_fixed_variant`, running along the edge of a section or viewport. It reads as ambient telemetry — metadata, secondary navigation, or status information presented as a persistent, low-priority stream. The rhythm should be steady and smooth: not a carnival marquee, but a readout on a bridge console that you glance at when you need it and ignore when you don't.

---

## 6. Principles

### Do:
*   **Use Asymmetry:** Place images off-center. Let text overlap containers using the Glassmorphism rule. Composition should feel directed, not templated — every frame intentional.
*   **Embrace the Void:** Allow large areas of the screen to remain pure `surface_container_lowest`. Empty space isn't emptiness; it's the vacuum that makes the light meaningful.
*   **Color as Signal:** Use Core Red (#550000) only for the highest-priority interactive elements. It is the emergency frequency — overuse collapses its authority.

### Don't:
*   **No Rounded Corners:** Avoid `full` or `xl` corner radius. It reads as consumer-friendly in a way that undermines the tone. Stick to `sm` or `none` — engineered edges.
*   **No Pure White:** Never use #FFFFFF. Use `on_surface` (#e5e2e1) or `tertiary` (Hull Ivory) to maintain a tempered phosphor quality — light that has traveled a long way to reach you.
*   **No Standard Grids:** Do not align everything to a 12-column grid. Treat the viewport as a shot — compose it. Allow elements to breathe and occupy space with the confidence of a single object in a wide frame.

---

**Director's Final Note:**
Every element should feel like a choice made under constraint — the kind of restraint you see in a film where every prop, every light, every silence is load-bearing. The tone lives in the overlap: classic cinema's compositional discipline and science fiction's quiet assertion that the future is already here, just darker and more beautiful than expected. If a component looks like it came from a template, it isn't finished. Refine the depth. Sharpen the contrast. Remove what isn't earning its place in the frame.
