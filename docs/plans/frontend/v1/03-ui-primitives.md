# Plan 03: UI Primitive Components

> **Priority:** Must Have
> **Complexity:** L
> **Depends On:** Plan 02 (Design System CSS — tokens, typography, utilities)
> **Unlocks:** Plans 04, 06, 07, 08, 09, 10 (all domain components)

## Overview

Build the 11 global UI primitive components that form the design system's tangible interface. These components live in `app/components/ui/`, are auto-imported globally by Nuxt, and know nothing about movies, bookings, or any domain concept. Every domain component in the system composes one or more of these primitives.

## Reference Documents

- `docs/COMPONENT_INVENTORY.md` — Tier 1: Global Primitives (complete specs for all 11 components)
- `docs/DESIGN_SYSTEM_IMPLEMENTATION.md` — Sections 7 (component styling), 8 (focus indicators), 9 (guardrails)

---

## Tasks

### Task 1: CvButton

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/ui/CvButton.vue`
- **Details:**
  3 variants (primary, secondary, tertiary), 3 sizes (sm, default, lg), loading state with spinner, `href` renders as `<NuxtLink>`. Small variant gated behind `@media (pointer: fine)`.

  **Props:** `variant`, `size`, `disabled`, `loading`, `type`, `href`
  **Slots:** `default`, `icon-left`, `icon-right`
  **Events:** `click` (suppressed when disabled/loading)

  **Key design tokens:**
  - Primary: `background: var(--primary-container)`, `color: var(--secondary)`, `border-radius: 0.125rem`
  - Secondary: `background: var(--surface-container-high)`, `color: var(--on-surface)`
  - Tertiary: `background: transparent`, `color: var(--secondary)`, animated underline on hover
  - Active: `transform: scale(0.98)`, `duration-micro`
  - Focus: double-ring glow (`var(--secondary)`)
  - Loading: `aria-busy="true"`, spinner `aria-hidden="true"`
  - Disabled: `aria-disabled="true"`, reduced opacity

- **Acceptance Criteria:**
  - [ ] All 3 variants render with correct token values
  - [ ] `sm` size only renders on pointer-fine devices
  - [ ] Touch target minimum 3rem on mobile
  - [ ] Loading state shows spinner and prevents clicks
  - [ ] `href` prop renders `<NuxtLink>` instead of `<button>`
  - [ ] Focus ring visible on keyboard navigation

---

### Task 2: CvCard

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/ui/CvCard.vue`
- **Details:**
  3 surface tier variants (low, default, high), interactive mode with hover lift, `href` mode as `<NuxtLink>`.

  **Props:** `variant`, `interactive`, `href`
  **Slots:** `default`, `header`, `footer`

  **Key design tokens:**
  - Low: `--surface-container-low` (#1c1b1b), Default: `--surface-container` (#201f1f), High: `--surface-container-high` (#2a2a2a)
  - Edge catch: `--outline-variant` at 15% opacity
  - Hover lift: `translateY(-0.125rem)`, `duration-standard`, `ease-standard`
  - Padding: `var(--space-md)`
  - Border radius: `0.25rem`
  - No `box-shadow` on static cards (shadow only on hover for interactive)

- **Acceptance Criteria:**
  - [ ] 3 surface variants with correct background colors
  - [ ] Interactive card lifts on hover
  - [ ] `href` card is a `<NuxtLink>` with focus ring
  - [ ] No `border: 1px solid` — uses edge catch only

---

### Task 3: CvInput

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/ui/CvInput.vue`
- **Details:**
  Underline-only text input with gold focus glow. Supports v-model, error state, and multiple input types.

  **Props:** `modelValue`, `type`, `label`, `placeholder`, `error`, `disabled`, `required`, `size`
  **Events:** `update:modelValue`, `focus`, `blur`

  **Key design tokens:**
  - Underline unfocused: `border-bottom: 0.0625rem solid var(--outline)`
  - Underline focused: `var(--secondary)` with subtle gold glow
  - Label: `--type-label-md`, `--tertiary`, Newsreader
  - Error: `--type-label-md`, `--primary` (#FFB4A8)
  - No borders on top/left/right

  **Accessibility:** `for`/`id` pairing, `aria-invalid`, `aria-describedby` for errors, `aria-required`

- **Acceptance Criteria:**
  - [ ] v-model works correctly with all input types
  - [ ] Gold underline glow appears on focus
  - [ ] Error message displays below input with `aria-describedby` link
  - [ ] Label is always visible (not placeholder-only)

---

### Task 4: CvTextarea

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/ui/CvTextarea.vue`
- **Details:**
  Same pattern as CvInput but multi-line. Same underline styling and accessibility patterns.

  **Props:** `modelValue`, `label`, `placeholder`, `error`, `rows`, `disabled`, `required`

- **Acceptance Criteria:**
  - [ ] Multi-line input with configurable rows
  - [ ] Same visual treatment as CvInput (underline, gold focus, error)
  - [ ] Same accessibility patterns (label, aria-invalid, aria-describedby)

---

### Task 5: CvSelect

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/ui/CvSelect.vue`
- **Details:**
  Native `<select>` with underline treatment. Dropdown panel on `surface-container-high`.

  **Props:** `modelValue`, `options`, `label`, `placeholder`, `error`, `disabled`, `required`

  Uses native `<select>` for maximum screen reader compatibility.

- **Acceptance Criteria:**
  - [ ] Native select with custom underline styling
  - [ ] Placeholder option when no value selected
  - [ ] Same accessibility patterns as CvInput

---

### Task 6: CvModal

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/ui/CvModal.vue`
- **Details:**
  Dialog overlay with focus trap, glassmorphism backdrop, and Teleport to body.

  **Props:** `modelValue`, `title`, `size`, `closeable`
  **Slots:** `default`, `footer`

  **Key design tokens:**
  - Backdrop: `surface_variant` at 60% opacity + `backdrop-filter: blur(20px)`, z-modal-backdrop
  - Panel: `surface-container-high`, z-modal, `border-radius: 0.125rem`
  - Shadow: `0 20px 40px rgba(0, 0, 0, 0.6)`
  - Enter/exit: scale(0.95→1) + opacity, `duration-emphasis`

  **Accessibility:** `role="dialog"`, `aria-modal="true"`, focus trap, inert on background, Escape closes

- **Acceptance Criteria:**
  - [ ] Focus trap cycles within modal (Tab and Shift+Tab)
  - [ ] On open: focus moves to first focusable element
  - [ ] On close: focus returns to trigger element
  - [ ] Background receives `inert` attribute
  - [ ] Escape key closes modal when `closeable` is true
  - [ ] Glassmorphism backdrop renders correctly
  - [ ] Reduced motion: instant appear/disappear

---

### Task 7: CvAccordion

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/ui/CvAccordion.vue`
- **Details:**
  Expandable section with `grid-template-rows: 0fr → 1fr` animation.

  **Props:** `title`, `open`, `id`
  **Events:** `toggle`

  **Accessibility:** Button header with `aria-expanded`, `aria-controls`; content panel with `role="region"`, `aria-labelledby`

- **Acceptance Criteria:**
  - [ ] Smooth expand/collapse animation using grid-template-rows
  - [ ] Enter/Space toggles open/close
  - [ ] `aria-expanded` updates correctly
  - [ ] Reduced motion: instant toggle

---

### Task 8: CvBadge

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/ui/CvBadge.vue`
- **Details:**
  3 variants (default, accent, warning), 2 sizes (sm, default). Purely visual.

  **Key tokens:**
  - Default: `--surface-container-high` bg, `--tertiary` text
  - Accent: `--primary-container` bg, `--primary` text
  - Warning: `--secondary-container` bg, `--on-surface` text (contrast remediation)
  - Border radius: `0.125rem`

- **Acceptance Criteria:**
  - [ ] All 3 variants with correct token values
  - [ ] Both sizes render correctly
  - [ ] No interactive behavior

---

### Task 9: CvIcon

- **MoSCoW:** Must Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/ui/CvIcon.vue`
- **Details:**
  Icon wrapper with 4 sizes (sm 1rem, md 1.5rem, lg 3rem, xl 4rem). When `label` prop is set: `role="img"` + `aria-label`. When no label: `aria-hidden="true"`.

  **Icon source decision:** Use inline SVGs, an icon sprite, or a library like `unplugin-icons`. Decision to be made at implementation time based on available icon needs.

- **Acceptance Criteria:**
  - [ ] 4 size variants render at correct dimensions
  - [ ] Decorative icons are `aria-hidden="true"`
  - [ ] Meaningful icons have `role="img"` and `aria-label`

---

### Task 10: CvSkeletonLoader

- **MoSCoW:** Should Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/ui/CvSkeletonLoader.vue`
- **Details:**
  4 variants (text, card, image, circle). Shimmer animation (1500ms, linear, infinite). Reduced motion: solid fill.

  **Props:** `variant`, `width`, `height`, `lines`

  **Accessibility:** Container has `aria-busy="true"`, screen readers announce "Loading"

- **Acceptance Criteria:**
  - [ ] 4 shape variants render correctly
  - [ ] Shimmer animation plays on capable devices
  - [ ] Solid fill (no animation) when prefers-reduced-motion is active
  - [ ] `aria-busy="true"` set on container

---

### Task 11: CvToast

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/ui/CvToast.vue`
- **Details:**
  3 types (info, success, error). Auto-dismiss with configurable duration. Slide in/out animation.

  **Props:** `message`, `type`, `duration`
  **Events:** `dismiss`

  **Key tokens:**
  - Background: `--surface-container-high`
  - Success accent: left border `--secondary`
  - Error accent: left border `--primary-container`
  - z-index: `--z-toast` (600)
  - Enter: `translateY(100%→0)` + opacity
  - Exit: `translateY(0→100%)` + opacity

  **Accessibility:**
  - Info/success: `role="status"`, `aria-live="polite"`
  - Error: `role="alert"`, `aria-live="assertive"`

- **Acceptance Criteria:**
  - [ ] All 3 types with correct accent colors
  - [ ] Auto-dismisses after duration
  - [ ] Persistent toast when `duration` is 0
  - [ ] Dismiss button with `aria-label="Dismiss notification"`
  - [ ] Correct `role` and `aria-live` per type

---

## Testing Requirements

- **Unit Tests:** Component tests for interactive behavior:
  - CvModal: focus trap cycling, Escape key, backdrop click
  - CvAccordion: toggle state, keyboard interaction
  - CvButton: click suppression when disabled/loading
  - CvInput: v-model binding, error state rendering
  - CvToast: auto-dismiss timer, manual dismiss
- **Accessibility Audit:** Each component meets WCAG 2.1 AA:
  - Color contrast ratios (verify with design tokens)
  - Keyboard navigability
  - Screen reader announcements
  - Focus indicator visibility

## Dependencies Map

All 11 components are independent of each other but all depend on Plan 02 (CSS tokens). They can be built in parallel, but the recommended order is:

```
CvButton → CvCard → CvInput → CvTextarea → CvSelect (form family)
CvModal → CvAccordion (overlay/expand family)
CvBadge → CvIcon (simple display)
CvSkeletonLoader → CvToast (feedback family)
```

## Risks & Open Questions

1. **Icon strategy** — Need to decide on icon source (SVG sprites, unplugin-icons, or manual SVGs). Recommendation: start with inline SVG components, migrate to unplugin-icons if the icon set grows large.
2. **Focus trap implementation** — CvModal needs a robust focus trap. Consider using `@vueuse/core`'s `useFocusTrap` or implementing manually with `MutationObserver`.
3. **Teleport SSR** — CvModal uses `<Teleport to="body">`. Verify this works correctly with Nuxt SSR (wrap in `<ClientOnly>` if needed).
4. **CvToast container** — The toast rendering container needs to live in the default layout. Ensure toasts appear above all other z-index layers.
