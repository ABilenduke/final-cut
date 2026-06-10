# Plan 02 (v3) — Site contacts CMS

**Step:** 3.2 · **Status:** ✅ Complete

## Goal

Move the operational contact details that were hardcoded test data across the
public site — the footer line on every page, plus the support inboxes on the
accessibility/terms/privacy/careers/gift-card surfaces — into the
`site_settings` keyed store (Plan 15's infrastructure) behind an admin form.

## Design

- **Blob**: `SiteSettingsService::KEY_SITE_CONTACTS = 'site_contacts'`. Flat
  camelCase shape owned by the frontend (`frontend/app/data/siteContacts.ts`
  `SiteContacts` interface): `footerVenueName/footerAddress/footerPhone`,
  `generalEmail/privacyEmail/careersEmail/conciergeEmail`,
  `accessibilityEmail/accessibilityPhone`.
- **API**: `GET /api/site-content/contacts` on the existing
  `SiteContentController` — versioned 5-minute cache, `contacts: null` until
  the first save.
- **Admin**: new `SiteContacts` Filament page (Content group) mirroring
  `HomePageContent` — `CONTACT_DEFAULTS` prefill, email validation, gated on
  the existing `content.site_settings.update` permission.
- **Frontend**: `useSiteContacts()` + `resolveSiteContacts()` in
  `useSiteContent.ts`; `fallbackSiteContacts` (the previous hardcoded values)
  as render fallback. Consumers: `SiteFooter`, `accessibility.vue` (with the
  new `telHref()` helper for the phone link), `terms.vue`, `privacy.vue`,
  `careers.vue`, `gift-cards.vue`, `gift-cards/bulk.vue`, `GiftCardPreview`.
- **Route rule**: `/accessibility` flipped prerender → `isr: 1800` — its
  contact line is now API-served and must be able to update without a build.

## Out of scope

`BridgeCinemaReadout` (what's-on telemetry) stays a static stub per its v1
spec — it needs a live-operations data source, not an editorial blob.

## Tests

- `backend/tests/Feature/Admin/Services/SiteContactsTest.php` — API
  null-until-saved, page permission matrix, prefill → save → API round trip,
  saved-over-defaults reload + cache bust, email validation (5 tests).
- `frontend/tests/components/layout/SiteFooter.test.ts` — saved vs fallback.
- `frontend/tests/composables/useSiteContent.test.ts` — contacts fetch
  contract + resolution rule.
- Updated mocks: `static-pages.test.ts` (contacts path → null fixture),
  `GiftCardPreview.test.ts` (transport mock).
