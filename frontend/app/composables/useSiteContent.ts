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

/** Wire shape of GET /api/site-content/careers. */
export interface CareersContentResponse {
  data: {
    /** Null until an admin saves the Careers content form. */
    benefits: string[] | null
  }
}

/**
 * SSR-friendly fetch wrapper for the admin-editable careers "why work here"
 * benefits (admin-v6 G5). Explicit key dedupes within one request graph.
 */
export function useCareersContent() {
  return useApiFetch<CareersContentResponse>('/api/site-content/careers', {
    key: 'site-content-careers',
  })
}

/**
 * Prefer the admin-saved benefits; fall back to the built-in list when nothing
 * is saved yet, the API is unreachable, or the saved list is empty — so the
 * "Why Work Here" section never renders blank.
 */
export function resolveCareersBenefits(
  saved: string[] | null | undefined,
  fallback: string[],
): string[] {
  return saved && saved.length > 0 ? saved : fallback
}

/** Contact-page "getting here" prose. */
export interface ContactInfo {
  byCar: string
  byTransit: string
  accessibility: string
}

/** Wire shape of GET /api/site-content/contact-info. */
export interface ContactInfoResponse {
  data: {
    /** Null until an admin saves the Contact content form. */
    contactInfo: ContactInfo | null
  }
}

/**
 * SSR-friendly fetch wrapper for the admin-editable contact-page "getting
 * here" prose (admin-v6 G6). Explicit key dedupes within one request graph.
 */
export function useContactInfo() {
  return useApiFetch<ContactInfoResponse>('/api/site-content/contact-info', {
    key: 'site-content-contact-info',
  })
}

/** Prefer the admin-saved prose; fall back to the built-in copy. */
export function resolveContactInfo(
  saved: ContactInfo | null | undefined,
  fallback: ContactInfo,
): ContactInfo {
  return saved ?? fallback
}

/** Private-screenings page intro copy. */
export interface PrivateScreeningsCopy {
  title: string
  intro: string
}

/** Wire shape of GET /api/site-content/private-screenings. */
export interface PrivateScreeningsResponse {
  data: {
    /** Null until an admin saves the Private screenings content form. */
    privateScreenings: PrivateScreeningsCopy | null
  }
}

/**
 * SSR-friendly fetch wrapper for the admin-editable private-screenings page
 * intro (admin-v6 G3). Explicit key dedupes within one request graph.
 */
export function usePrivateScreeningsCopy() {
  return useApiFetch<PrivateScreeningsResponse>('/api/site-content/private-screenings', {
    key: 'site-content-private-screenings',
  })
}

/** Prefer the admin-saved intro; fall back to the built-in copy. */
export function resolvePrivateScreeningsCopy(
  saved: PrivateScreeningsCopy | null | undefined,
  fallback: PrivateScreeningsCopy,
): PrivateScreeningsCopy {
  return saved ?? fallback
}

/** Accessibility-page prose — intro + the six section paragraphs. */
export interface AccessibilityStatement {
  intro: string
  assistedListening: string
  wheelchairSeating: string
  openCaption: string
  audioDescription: string
  sensoryFriendly: string
  serviceAnimals: string
}

/** Wire shape of GET /api/site-content/accessibility. */
export interface AccessibilityResponse {
  data: {
    /** Null until an admin saves the Accessibility content form. */
    accessibility: AccessibilityStatement | null
  }
}

/**
 * SSR-friendly fetch wrapper for the admin-editable accessibility-page prose
 * (admin-v6 G4). Explicit key dedupes within one request graph.
 */
export function useAccessibilityStatement() {
  return useApiFetch<AccessibilityResponse>('/api/site-content/accessibility', {
    key: 'site-content-accessibility',
  })
}

/** Prefer the admin-saved prose; fall back to the built-in copy. */
export function resolveAccessibilityStatement(
  saved: AccessibilityStatement | null | undefined,
  fallback: AccessibilityStatement,
): AccessibilityStatement {
  return saved ?? fallback
}

/** A header/footer navigation entry. */
export interface NavItem {
  label: string
  href: string
}

/** Wire shape of GET /api/site-content/navigation. */
export interface NavigationResponse {
  data: {
    /** Null until an admin saves the Navigation form. */
    header: NavItem[] | null
    footer: NavItem[] | null
  }
}

/**
 * SSR-friendly fetch wrapper for the admin-editable header/footer nav
 * (admin-v6 G1). The shared key dedupes the single fetch across the header
 * and footer, which both consume it in the same request graph.
 */
export function useNavigation() {
  return useApiFetch<NavigationResponse>('/api/site-content/navigation', {
    key: 'site-content-navigation',
  })
}

// A nav href is safe to render only if it's a site-relative path or an
// absolute http(s) URL — never a javascript:/data: scheme. Defence-in-depth
// alongside the admin form's save-time guard.
function isSafeNavHref(href: unknown): href is string {
  return typeof href === 'string' && (href.startsWith('/') || /^https?:\/\//.test(href))
}

/**
 * Prefer the admin-saved nav; fall back to the built-in list when nothing is
 * saved, the API is unreachable, or every saved item is malformed/unsafe — so
 * the layout shell never renders an empty nav. Individual malformed/unsafe
 * items are dropped.
 */
export function resolveNavItems(
  saved: NavItem[] | null | undefined,
  fallback: NavItem[],
): NavItem[] {
  if (!Array.isArray(saved)) return fallback

  const safe = saved.filter(
    (item): item is NavItem =>
      Boolean(item) && typeof item.label === 'string' && item.label.length > 0 && isSafeNavHref(item.href),
  )

  return safe.length > 0 ? safe : fallback
}
