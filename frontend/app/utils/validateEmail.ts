/**
 * Lightweight RFC-friendly email format check for client-side validation.
 * Backend validation (Laravel `email` rule) remains the source of truth —
 * this only catches obvious typos before submit so users see errors faster.
 */
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function isValidEmail(value: string): boolean {
  return EMAIL_REGEX.test(value.trim())
}
