---
name: finalcut-design
description: Use this skill to generate well-branded interfaces and assets for Final Cut, the cinematic theatre brand ("The Cinematic Void Framework"), either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping.
user-invocable: true
---

This skill applies to work under `frontend/`. The live design system is in this same repo — there is no parallel UI kit shipped with the skill. Defer to:

- **Tokens / CSS:** `frontend/app/assets/css/tokens.css`, `typography.css`, `utilities.css`, `layouts.css` — the canonical token + utility set.
- **Vue UI primitives:** `frontend/app/components/ui/*.vue` (CvButton, CvCard, CvInput, CvBadge, CvCheckbox, CvIcon, CvModal, CvSelect, CvTextarea, CvAccordion, CvSkeletonLoader, CvToast).
- **Layout shell:** `frontend/app/components/layout/*.vue` (SiteHeader, SiteFooter, NeuralTicker, SidebarNav, MobileNav, SkipNav).
- **Feature components:** `frontend/app/components/{movie,booking,calendar,account,home,content}/*.vue`.
- **Icons:** `frontend/app/components/ui/icons.ts` (path map) + `CvIcon.vue` (the renderer). Use `<CvIcon name="..." />` in Vue.
- **Design system docs:** `docs/design-system/DESIGN_SYSTEM.md`, `DESIGN_SYSTEM_STRUCTURE.md`, `DESIGN_SYSTEM_IMPLEMENTATION.md`.

Read `README.md` for context and content/visual rules. Read `references/components.md` for a catalog mapping component → file path → purpose. Use `colors_and_type.css` and `assets/icons/` only for the rare cases where you need a self-contained HTML artifact (slide deck, one-off mock outside the Nuxt app).

## Quick start (production work)
- Building a new page or component? Add it to `frontend/app/pages/` or `frontend/app/components/` and compose existing primitives. Don't write parallel CSS — use the tokens.
- Adding a route? Set the rendering strategy via `routeRules` in `frontend/nuxt.config.ts` per `docs/architecture/SITE_ARCHITECTURE.md`.
- Need a new icon? Add it to `frontend/app/components/ui/icons.ts` (Material Design Icons, Apache 2.0). Then re-derive the bundled `assets/icons/icons.js` + `sprite.svg` if you want the skill's offline copy to stay current.
- Always test against `make test-frontend` and (for visual work) `make e2e`.

## Token discipline (the one rule that matters)
`primary` (#FFB4A8, salmon) is a **text color** — only use it for foregrounds on top of `primary_container` fills. `primary_container` (#550000, reactor maroon) is the **fill** color for buttons, active accents, hero vignettes. Confusing these is the single most common mistake.

See the "CRITICAL: Token Mapping" section of the project `docs/design-system/DESIGN_SYSTEM.md` for the full do/don't table.

## Creative direction
"The Final Cut" — Kubrick composition, Ridley Scott darkness, the enormity of a ship moving through empty space. Deep vacuum-black surfaces, intentional asymmetry, text as title cards. No pure white, no rounded corners, no dividing lines, no shadows on static elements. Reactor maroon is the emergency frequency — overuse collapses its authority.

## What to do
**Production code is the default mode.** Edit the real Vue components in `frontend/app/components/` and the pages in `frontend/app/pages/`. Compose existing primitives. Apply the tokens, layout compositions, and content-style rules in this skill's `README.md`. Never ship parallel CSS or duplicate primitives.

**Self-contained HTML artifacts are the rare exception** — slide decks, throwaway mocks outside the Nuxt app, or anything that has to live as a single file. In that case, link `colors_and_type.css`, pull icons from `assets/icons/`, and treat the live Vue components in `frontend/app/components/` as the visual reference for layout, spacing, and state styling.

If the user invokes this skill without specific guidance, ask what they want to build, where it should live (production page/component, or a one-off HTML file), and act as an expert designer working within the existing system.
