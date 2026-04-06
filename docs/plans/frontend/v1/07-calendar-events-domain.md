# Plan 07: Calendar & Events Domain

> **Priority:** Should Have
> **Complexity:** L
> **Depends On:** Plan 03 (UI primitives), Plan 04 (layouts), Plan 05 (useCalendarEvents composable)
> **Unlocks:** None (leaf node)

## Overview

Build the calendar and events experience: 6 domain components and 3 pages. This feature surfaces the theater's event programming — showtimes, special events, loyalty exclusives, and accessibility-filtered screenings — through a calendar interface and event detail pages.

## Reference Documents

- `docs/COMPONENT_INVENTORY.md` — Tier 2: Domain Components — Calendar + EventListCard, EventDetail (Content)
- `docs/PAGE_SPECS.md` — What's On (`/whats-on`), Events (`/events`), Event Detail (`/events/:slug`)
- `docs/DATA_MODELS.md` — CalendarEvent, AccessibilityTag interfaces

---

## Tasks

### Task 1: CalendarFilters

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/calendar/CalendarFilters.vue`
  - `frontend/app/components/calendar/CalendarFilters.stories.ts`
- **Details:**
  View toggle (month/week/list) and filter controls.

  **Props:** `activeView`, `activeFilters` (event types), `activeAccessibilityFilters` (AccessibilityTag[])
  **Events:** `view-change`, `filter-change`, `accessibility-filter-change`

  Event type checkboxes: showtimes, special events, loyalty exclusives.
  Accessibility checkboxes in plain language: "Sensory Friendly", "Open Captions", "Audio Described".

  URL encoding: `?accessibility=sensory_friendly,open_caption` (comma-separated, canonical format).

- **Acceptance Criteria:**
  - [ ] View toggle between month/week/list
  - [ ] Event type filter checkboxes work
  - [ ] Accessibility filter checkboxes with plain language labels
  - [ ] Filters emit correct events on change

---

### Task 2: CalendarDayCell

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/calendar/CalendarDayCell.vue`
  - `frontend/app/components/calendar/CalendarDayCell.stories.ts`
- **Details:**
  **Props:** `date`, `events`, `selected`, `today`

  Day number with event indicator dots color-coded by type:
  - Showtime: `--tertiary` (#CCC6B6)
  - Special event: `--secondary` (#DAC769)
  - Loyalty exclusive: `--primary-container` (#550000)

  Accessibility events show additional icon indicator. Minimum cell size: 3rem (48px).

- **Acceptance Criteria:**
  - [ ] Day number displays correctly
  - [ ] Event dots color-coded by type
  - [ ] Selected state visually distinct
  - [ ] Today highlighted
  - [ ] Meets 3rem minimum touch target

---

### Task 3: CalendarGrid

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/components/calendar/CalendarGrid.vue`
  - `frontend/app/components/calendar/CalendarGrid.stories.ts`
- **Details:**
  Month/week grid with keyboard navigation.

  **Props:** `events: CalendarEvent[]`, `selectedDate: string`, `view: 'month' | 'week' | 'list'`
  **Events:** `select-date`, `navigate` (month/year change)

  **Accessibility:** `role="grid"`, roving tabindex with arrow keys, Page Up/Down for month, Home/End for first/last day. Column headers: `role="columnheader"` with day names. Each cell: `aria-label="[Full date]. [N] events."`, `aria-selected`.

- **Acceptance Criteria:**
  - [ ] Month view renders full calendar grid
  - [ ] Week view renders 7-day strip
  - [ ] List view renders chronological event list
  - [ ] Date navigation (previous/next month)
  - [ ] Keyboard navigation (arrows, Page Up/Down, Home/End)
  - [ ] ARIA grid pattern implemented correctly

---

### Task 4: CalendarEventList

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/calendar/CalendarEventList.vue`
  - `frontend/app/components/calendar/CalendarEventList.stories.ts`
- **Details:**
  Events for a selected day, grouped by type.

  **Props:** `events: CalendarEvent[]`, `date: string`

  Each event shows time, title, type badge (CvBadge), accessibility badge (if applicable), and link to detail.

- **Acceptance Criteria:**
  - [ ] Events grouped by type
  - [ ] Each event shows time, title, type badge
  - [ ] Accessibility badges display when applicable
  - [ ] Events link to detail page or purchase page (for showtimes)

---

### Task 5: EventListCard + EventDetail

- **MoSCoW:** Must Have
- **Complexity:** S
- **Files:**
  - `frontend/app/components/content/EventListCard.vue`
  - `frontend/app/components/content/EventDetail.vue`
  - Stories for each
- **Details:**
  **EventListCard:** CvCard with event image (4:3), date badge, title, description preview, type badge, "Learn More" link.

  **EventDetail:** Full event page. Title, date/time, description, what's included list, pricing, CTA button ("Get Tickets" or "RSVP").

- **Acceptance Criteria:**
  - [ ] EventListCard displays all event summary fields
  - [ ] EventDetail renders full event content
  - [ ] CTA links to ticket purchase or RSVP as appropriate

---

### Task 6: What's On Page (`/whats-on`)

- **MoSCoW:** Must Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/whats-on.vue`
- **Details:**
  Per PAGE_SPECS.md. Layout: `default`. Rendering: ISR (15 min). Wide Frame composition.

  **Sections:**
  1. CalendarFilters (view toggle, event type filters, accessibility filters)
  2. CalendarGrid (month grid with event dots)
  3. CalendarEventList (events for selected day)

  **URL as state:** `?month=4&year=2026&type=special_event&accessibility=sensory_friendly,open_caption`

  **Data:** `GET /api/calendar/events?month=M&year=Y&type=filter`

  **SEO:** Title: `What's On — Final Cut`. Structured data: `Event`.

- **Acceptance Criteria:**
  - [ ] Calendar grid renders with event indicators
  - [ ] Filters update URL and refetch data
  - [ ] Selecting a day shows that day's events
  - [ ] Accessibility filters work with comma-separated URL encoding
  - [ ] Deep links to filtered views work (e.g., from accessibility page)

---

### Task 7: Events Pages (`/events`, `/events/:slug`)

- **MoSCoW:** Should Have
- **Complexity:** M
- **Files:**
  - `frontend/app/pages/events/index.vue`
  - `frontend/app/pages/events/[slug].vue`
- **Details:**
  **Events listing (`/events`):** Layout: `default`. ISR (15 min).
  - Featured event (Wide Frame hero)
  - Upcoming events (Ensemble grid with asymmetric hierarchy)

  **Event detail (`/events/:slug`):** Layout: `default`. ISR (15 min).
  - Wide Frame hero image
  - Close-Up body with EventDetail component

  **Data:** `GET /api/calendar/events?type=special_event`, `GET /api/calendar/events/:slug`

  **SEO:** Structured data: `Event`, `ItemList` for listing.

- **Acceptance Criteria:**
  - [ ] Events listing shows featured event prominently
  - [ ] Upcoming events in grid layout
  - [ ] Event detail page renders full content
  - [ ] CTA buttons link to relevant action
  - [ ] Structured data renders for SEO

---

## Testing Requirements

- **Storybook:** Stories for CalendarFilters, CalendarDayCell, CalendarGrid, CalendarEventList, EventListCard, EventDetail
  - Calendar grid with various event distributions
  - All filter states
  - Responsive layouts
- **E2E Tests:**
  - Navigate calendar: change month, select day, view events
  - Filter by event type and accessibility tag
  - Deep link with filter params
  - Navigate to event detail from calendar
- **Accessibility:**
  - Calendar keyboard navigation (arrows, Page Up/Down)
  - Screen reader announces day with event count
  - Filter changes announced

## Dependencies Map

```
Task 1 (CalendarFilters) ← independent
Task 2 (CalendarDayCell) ← independent
Task 3 (CalendarGrid) ← uses Task 2
Task 4 (CalendarEventList) ← uses CvBadge
Task 5 (EventListCard + EventDetail) ← uses CvCard, CvBadge, CvButton
Task 6 (What's On Page) ← uses Tasks 1, 3, 4
Task 7 (Events Pages) ← uses Task 5
```

## Risks & Open Questions

1. **Calendar library** — Building a full calendar grid from scratch is complex. Consider using a lightweight library or building a minimal custom grid. Recommendation: build custom for design system consistency.
2. **Week and list views** — The spec mentions month, week, and list views. Week and list are simpler but still require separate rendering. Prioritize month view first; week/list can follow.
3. **Accessibility filter deep links** — The accessibility page (`/accessibility`) links to pre-filtered calendar views. Ensure the URL state system handles these incoming links correctly.
4. **Week/list views are client-side projections** — The backend only supports `?month=M&year=Y` queries. Week and list views are computed by filtering month results client-side. Cross-month weeks may show incomplete data for v1 — this is a deliberate trade-off.
