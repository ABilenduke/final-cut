# Plan 04 (v5) — Calendar Week/List views

**Step:** 5.4 · **Status:** ✅ Complete

## Goal

Enable the two "Coming soon" view modes on the what's-on Bridge Console.
The page already had the full plumbing (`?view=` query state, toolbar
`view-change` events) — only the renderings were missing.

## Design

- **`BridgeWeekStrip`** — the Monday-start week containing the selected
  date as seven columns: weekday + day numeral (today gold, selected
  filled), full per-day event listings (no `+N more` cap), type-color left
  borders via the shared `chipSlugForEvent`/`FC_TYPE_COLORS`. Days outside
  the loaded month render muted (the month payload doesn't cover them).
  Clicking a day selects it — the detail rail/drawer contract is identical
  to the month grid. Collapses to a single column below `screen-md`.
- **`BridgeAgendaList`** — the visible month as a chronological agenda
  grouped by day (clickable date headings select the day; today gets a
  marker; quiet empty state under heavy filters).
- Both consume the same `visibleEvents` (chip-filtered) and the
  page-provided `todayDate` prop — no argless `new Date()` (date-hydration
  rule). Prev/today/next stay month-based in all views.
- Toolbar Week/List options un-disabled; the stale "disabled" test pin
  inverted to assert they're live.

## Tests

`BridgeWeekStrip.test.ts` (Mon-start week boundaries, uncapped listings +
select-date, today/selected marking) and `BridgeAgendaList.test.ts`
(chronological grouping + select-date, empty state); toolbar pin updated.
