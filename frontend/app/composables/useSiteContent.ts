import type { MembershipContent } from '~/data/homepage'
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
