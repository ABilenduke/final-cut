# Plan 12: Storybook Comprehensive

> **Priority:** Should Have
> **Complexity:** M
> **Depends On:** Plans 03–10 (all component plans)
> **Unlocks:** None (leaf node)

## Overview

Configure Storybook for the project and ensure comprehensive story coverage across all components. While individual component plans include their own `.stories.ts` files, this plan addresses the overall Storybook setup, design system documentation stories, gap coverage, and interaction testing.

## Reference Documents

- `docs/DESIGN_SYSTEM.md` — Color palette, typography, principles
- `docs/DESIGN_SYSTEM_IMPLEMENTATION.md` — Token values, layout compositions
- `docs/COMPONENT_INVENTORY.md` — All components and their variants

---

## Tasks

### Task 1: Storybook Configuration

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/.storybook/main.ts` — Storybook config
  - `frontend/.storybook/preview.ts` — Global decorators, theme
- **Details:**
  Configure Storybook with:
  - Nuxt Storybook module/framework adapter
  - Dark theme matching the design system (`--surface` background, `--on-surface` text)
  - Viewport presets matching breakpoints: Mobile (320px), Tablet (640px), Desktop (960px), Wide (1280px)
  - Global decorator wrapping stories in design system CSS
  - Auto-import of design system tokens

- **Acceptance Criteria:**
  - [ ] Storybook starts and renders components
  - [ ] Dark theme applied (matches app background)
  - [ ] Viewport presets available
  - [ ] CSS tokens available in all stories

---

### Task 2: Design System Documentation Stories

- **MoSCoW:** Should Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/ui/DesignSystem.stories.ts` — or `stories/` directory
- **Details:**
  Reference stories (not interactive components) documenting:
  - **Color palette:** All color tokens with swatches and labels
  - **Typography scale:** All type tokens rendered at their sizes with font family
  - **Spacing scale:** Visual representation of all spacing tokens
  - **Layout compositions:** Examples of Establishing Shot, Rack Focus, Wide Frame, Close-Up, Ensemble, Auditorium
  - **Icon sizes:** All 4 icon size tokens
  - **Z-index scale:** Visual stack order reference
  - **Motion tokens:** Easing curves and duration values

- **Acceptance Criteria:**
  - [ ] Color palette story shows all tokens with hex values
  - [ ] Typography story renders all 15 type scale levels
  - [ ] Layout compositions demonstrated with placeholder content
  - [ ] Stories serve as living documentation for the design system

---

### Task 3: Story Gap Audit

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - Various `.stories.ts` files across all component directories
- **Details:**
  Audit all components from Plans 03–10 and ensure each has stories covering:
  - All prop variants and combinations
  - All interactive states (default, hover, focus, active, disabled, loading, error)
  - Responsive behavior at mobile/tablet/desktop viewports
  - Reduced motion behavior
  - Empty/loading/error data states
  - Realistic data (not "Lorem ipsum")

  Components most likely to have gaps: domain components from Plans 06–10 that were built with focus on functionality over documentation.

- **Acceptance Criteria:**
  - [ ] Every component from Plans 03–10 has at least one story
  - [ ] Primitive components (Plan 03) have stories for every variant
  - [ ] Domain components have stories with realistic data
  - [ ] No broken stories in the Storybook build

---

### Task 4: Interaction Tests (Storybook Play Functions)

- **MoSCoW:** Could Have
- **Complexity:** M
- **Files:**
  - Various `.stories.ts` files
- **Details:**
  Add Storybook play functions for complex interactive components:
  - **CvModal:** Open modal, verify focus trap, Escape to close, verify focus returns
  - **CvAccordion:** Click to expand, verify content visible, click to collapse
  - **AuditoriumGrid:** Click seat, verify selection, arrow key navigation
  - **CheckoutForm:** Fill fields, submit, verify validation
  - **ShowtimeSelector:** Tab navigation, date selection, time slot click

- **Acceptance Criteria:**
  - [ ] Play functions automate key interactions
  - [ ] Tests pass in Storybook test runner
  - [ ] Focus management verified for modal and grid

---

## Testing Requirements

- **Storybook Build:** `npx storybook build` completes without errors
- **Visual Regression:** Optional — set up Chromatic or Percy for visual snapshot testing

## Risks & Open Questions

1. **Storybook + Nuxt 4** — Verify `@nuxtjs/storybook` or the appropriate adapter works with Nuxt 4. May need manual Vite config.
2. **Docker integration** — Storybook runs as a separate container in the Docker setup. Verify the container can access component source via volume mounts.
