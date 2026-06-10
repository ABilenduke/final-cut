# Plan 15 — Site settings + home membership pitch

**Step:** 2.5 · **Status:** ✅ Complete

## Goal

Give admins an editorial form for the home page membership pitch — the last
hardcoded copy block on the home page apart from the hero's sample showtime
chips (Plan 16). Establish a generic keyed `site_settings` store so future
editorial blobs (hero copy, footer copy, announcement bars) reuse the same
table, service, and cache plumbing instead of growing one table per blob.

## Design

### Backend

- **`site_settings` table** — `key` (string, PK), `value` (json), nullable
  `updated_by` FK → users (null on delete), timestamps. One row per blob; the
  value's shape is owned by the consuming frontend surface and passes through
  the API untransformed (camelCase keys, the frontend's wire contract).
- **`SiteSetting` model** — string PK, `value` array cast. No factory needed;
  rows are created exclusively through the service.
- **`SiteSettingsService`** — the write boundary. `get(key, default)` /
  `set(key, value, User $actor)`; `set` runs in a transaction, stamps
  `updated_by`, and logs `site_settings.updated` activity with the key as a
  property. Constant `KEY_HOME_MEMBERSHIP = 'home_membership'`.
- **`SiteSettingObserver`** — versioned-key cache invalidation
  (`site_settings_public_version`), registered in `AppServiceProvider` and
  added to `RefreshContentCacheVersions::$keys`.
- **`GET /api/site-content/home`** — versioned `Cache::remember(…, 300)`
  returning `{ membership: blob|null }`. Null until the first admin save —
  the frontend renders its built-in copy as the fallback (same contract as
  ticker/featured slides).
- **`HomePageContent` Filament page** (Content group) — schema-first form
  over `statePath('data')`: pitch fields, perks Repeater (1–6, reorderable),
  collapsed card-art section. `MEMBERSHIP_DEFAULTS` const mirrors
  `frontend/app/data/homepage.ts` so the first edit starts from what
  visitors currently see; saved values win on subsequent mounts. Access +
  save both gate on the new `content.site_settings.update` permission
  (admin + manager; ops excluded).

### Frontend

- **`useSiteContent.ts`** — `useHomeContent()` (`useApiFetch`, key
  `site-content-home`) + pure `resolveMembershipContent(saved, fallback)`.
- **`HomeMembership.vue`** — consumes the composable; `membershipContent`
  from `data/homepage.ts` remains the fallback. No template changes.

## Decisions

- **Generic keyed store, not a `home_membership` table.** The blob is pure
  editorial copy with no relational structure; jsonb + a service-enforced
  shape costs one table for every future blob instead of N.
- **Single `content.site_settings.update` permission** (no `.view`): the
  page is a write surface; anyone who may see it may save it.
- **Defaults duplicated backend-side** (`MEMBERSHIP_DEFAULTS`): accepted
  duplication so the form never opens blank. The authoritative fallback for
  rendering stays in the frontend; the backend copy only seeds the form.

## Tests

- `backend/tests/Feature/Admin/Services/SiteSettingsTest.php` — service
  round-trip + actor stamp + activity; API null-until-saved; cache bust on
  set; page permission matrix (admin/manager yes, ops no); page prefill →
  save → API visibility; saved values reload over defaults; required-field
  validation.
- `frontend/tests/composables/useSiteContent.test.ts` — fetch contract +
  resolution rule.
- `frontend/tests/components/home/HomeMembership.test.ts` — renders saved
  blob; falls back to built-in copy on null.
