import type { MembershipContent } from '~/data/homepage'
import type { SiteContacts } from '~/data/siteContacts'
import { useApiFetch } from '~/utils/api'

/** Wire shape of GET /api/site-content/home. */
export interface HomeContentResponse {
  data: {
    /** Null until an admin saves the Home page content form. */
    membership: MembershipContent | null
  }
}

/**
 * SSR-friendly fetch wrapper for the admin-editable home page editorial
 * blobs (admin-v2 Plan 15). Returns the useFetch tuple — data, pending,
 * error, refresh.
 *
 * The explicit key 'site-content-home' dedupes the fetch across the home
 * page sections that consume it within one SSR request graph.
 */
export function useHomeContent() {
  return useApiFetch<HomeContentResponse>('/api/site-content/home', {
    key: 'site-content-home',
  })
}

/**
 * Pure resolution rule shared by HomeMembership and its tests: prefer the
 * admin-saved blob; fall back to the built-in copy when nothing has been
 * saved yet (or the API is unreachable) so the section never renders empty.
 */
export function resolveMembershipContent(
  saved: MembershipContent | null | undefined,
  fallback: MembershipContent,
): MembershipContent {
  return saved ?? fallback
}

/** Wire shape of GET /api/site-content/contacts. */
export interface SiteContactsResponse {
  data: {
    /** Null until an admin saves the Site contacts form. */
    contacts: SiteContacts | null
  }
}

/**
 * SSR-friendly fetch wrapper for the admin-editable site-wide contact
 * details (admin-v3 Plan 02). The footer consumes this on every page; the
 * explicit key dedupes the fetch across all consumers in one request graph.
 */
export function useSiteContacts() {
  return useApiFetch<SiteContactsResponse>('/api/site-content/contacts', {
    key: 'site-content-contacts',
  })
}

/** Prefer the admin-saved contacts; fall back to the built-in values. */
export function resolveSiteContacts(
  saved: SiteContacts | null | undefined,
  fallback: SiteContacts,
): SiteContacts {
  return saved ?? fallback
}
