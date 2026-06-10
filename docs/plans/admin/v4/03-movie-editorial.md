# Plan 03 (v4) — Movie editorial CMS

**Step:** 4.3 · **Status:** ✅ Complete

## Goal

Replace the last hardcoded movie-page stubs — the dash-only crew credits
(`MovieDetail`), the sample press quotes (`MoviePress`), and the decorative
placeholder clips (`MovieTrailerEmbed`) — with admin-authored content.

## Design

House precedent (`genres`/`cast`): three nullable JSON columns on `movies`
(in-place migration edit) rather than new tables — this is per-movie
editorial copy with no relational structure:

- **`credits`** — object of fixed crew fields (director, screenplay,
  cinematography, editor, composer, aspect, advisory).
- **`press_quotes`** — list of `{quote, author, publication}`. Quotes are
  stored as plain text; the component renders them as single-run segments
  (the sample quotes' italic emphasis is presentational only).
- **`clips`** — list of `{label, sub, duration, youtube_key}`.

Admin: a collapsed **Editorial** section on the movie form (credit text
inputs + two reorderable Repeaters). Uses the existing `movies.update`
permission. API: the detail `MovieResource` exposes `credits` (nullable),
`pressQuotes` and `clips` (empty arrays when unset).

Frontend: `MovieDetail` merges filled credit fields over the neutral
dashes (blank strings stay dashes); `MoviePress` takes a `quotes` prop and
falls back to the sample copy when empty; `MovieTrailerEmbed` takes
`extraClips` — real clips (playable YouTube keys) replace the decorative
placeholders, with the official trailer always first.

## Out of scope

- TMDB crew enrichment (the `credits` object could be auto-filled from the
  TMDB credits payload `movies:enrich` already fetches — future step).
- The `MoviePress` aggregate score bars stay static editorial design (no
  ratings source exists).

## Tests

- `backend/tests/Feature/Admin/Resources/MovieEditorialTest.php` — API
  exposure (camelCase + null/empty defaults) and admin form round trip.
- `frontend/tests/components/movie/MoviePress.test.ts` (new) — real quotes
  suppress samples; fallback renders samples.
- `MovieDetail.test.ts` / `MovieTrailerEmbed.test.ts` — credits merge with
  dash placeholders; real clips replace placeholder labels.
