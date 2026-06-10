// Site-wide contact details — admin-editable via /api/site-content/contacts
// (admin-v3 Plan 02); these values are the render fallback until the first
// admin save. Mirrored backend-side in Filament\Pages\SiteContacts::CONTACT_DEFAULTS.

export interface SiteContacts {
  footerVenueName: string
  footerAddress: string
  footerPhone: string
  generalEmail: string
  privacyEmail: string
  careersEmail: string
  accessibilityEmail: string
  accessibilityPhone: string
  conciergeEmail: string
}

export const fallbackSiteContacts: SiteContacts = {
  footerVenueName: 'Final Cut Theatre',
  footerAddress: '123 Cinema Boulevard',
  footerPhone: '(555) 123-4567',
  generalEmail: 'hello@finalcut.test',
  privacyEmail: 'privacy@finalcut.test',
  careersEmail: 'careers@finalcut.test',
  accessibilityEmail: 'accessibility@finalcut.test',
  accessibilityPhone: '212-555-0199',
  conciergeEmail: 'concierge@finalcut.test',
}

/** `tel:` href for a US-formatted display phone — strips punctuation, prefixes +1. */
export function telHref(phone: string): string {
  const digits = phone.replace(/\D/g, '')
  return `tel:+${digits.length === 10 ? `1${digits}` : digits}`
}
