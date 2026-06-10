# Plan 02 (v5) — TMDB crew enrichment

**Step:** 5.2 · **Status:** ✅ Complete

## Goal

Auto-fill the movie editorial credits (v4 Plan 03) from the TMDB credits
payload `movies:enrich` already fetches — the deferred follow-up noted in
that plan.

## Design

- `TmdbService::mapCrewCredits()` maps the crew list onto the credit
  fields: Director → director, Screenplay/Writer → screenplay, Director of
  Photography → cinematography, Editor → editor, Original Music Composer →
  composer. Multiple holders of one job join with a comma; unmapped jobs
  are ignored. `aspect`/`advisory` have no TMDB source and stay admin-only.
- Merge rule in the enrichment update (non-partial only, like cast):
  **TMDB fills the blanks, admin-authored values win** — blank admin
  strings count as unfilled. Partial enrichments leave credits untouched.

## Tests

Two added to `TmdbServiceTest`: crew payload fills all five mapped fields
(comma-join + unmapped-job exclusion) and admin-authored values surviving
enrichment while blanks get filled.
