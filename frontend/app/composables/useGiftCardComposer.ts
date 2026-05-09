import type { GiftCardDeliveryMethod, GiftCardEdition } from '~/types/gift-card'
import { isValidEmail } from '~/utils/validateEmail'

/**
 * Page-scoped shared state for the /gift-cards composer + preview surface.
 *
 * Backed by `useState` so SSR and client share the same reactive object across
 * `<GiftCardComposer>`, `<GiftCardPreview>`, and `<GiftCardVisual>` without
 * prop-drilling. Lives on this page only — no other surface reads it.
 */

export type ScheduleSlug = 'now' | 'tomorrow' | 'weekend' | 'custom'

export const PRESET_AMOUNTS_CENTS = [2500, 5000, 7500, 10000, 20000] as const
export const MIN_AMOUNT_CENTS = 2500
export const MAX_AMOUNT_CENTS = 50000
export const MAX_MESSAGE_LENGTH = 240

export interface EditionMeta {
  value: GiftCardEdition
  label: string
  serial: string
}

/**
 * Single source of truth for edition labels + the cosmetic serial number
 * shown on the gift-card visual. Consumed by `GiftCardComposer`,
 * `GiftCardPreview`, and `GiftCardVisual` so adding a fourth edition is a
 * one-line append.
 */
const EDITION_BY_VALUE: Record<GiftCardEdition, EditionMeta> = {
  reactor: { value: 'reactor', label: 'Reactor', serial: '047' },
  gold: { value: 'gold', label: 'Charter Gold', serial: '012' },
  void: { value: 'void', label: 'Pure Void', serial: '168' },
}

export const EDITIONS: readonly EditionMeta[] = Object.values(EDITION_BY_VALUE)

export function editionMeta(edition: GiftCardEdition): EditionMeta {
  return EDITION_BY_VALUE[edition]
}

export interface GiftCardComposerState {
  amountCents: number
  edition: GiftCardEdition
  deliveryMethod: GiftCardDeliveryMethod
  schedule: ScheduleSlug
  scheduleDateIso: string | null
  recipientName: string
  recipientEmail: string
  senderName: string
  message: string
}

export interface GiftCardComposerErrors {
  amount?: string
  recipientName?: string
  recipientEmail?: string
  senderName?: string
  scheduleDateIso?: string
}

const STATE_KEY = 'gift-cards:composer'
const ERRORS_KEY = 'gift-cards:composer-errors'

export function defaultState(): GiftCardComposerState {
  return {
    amountCents: 5000,
    edition: 'reactor',
    deliveryMethod: 'email',
    schedule: 'now',
    scheduleDateIso: null,
    recipientName: '',
    recipientEmail: '',
    senderName: '',
    message: '',
  }
}

export function useGiftCardComposer() {
  const state = useState<GiftCardComposerState>(STATE_KEY, defaultState)
  const errors = useState<GiftCardComposerErrors>(ERRORS_KEY, () => ({}))

  function setAmountCents(value: number): void {
    if (!Number.isFinite(value)) {
      state.value.amountCents = MIN_AMOUNT_CENTS
      return
    }
    state.value.amountCents = Math.max(0, Math.round(value))
  }

  function setEdition(edition: GiftCardEdition): void {
    state.value.edition = edition
  }

  function setDeliveryMethod(method: GiftCardDeliveryMethod): void {
    state.value.deliveryMethod = method
  }

  function setSchedule(slug: ScheduleSlug, customIso: string | null = null): void {
    state.value.schedule = slug
    state.value.scheduleDateIso = slug === 'custom' ? customIso : null
  }

  function setRecipientName(value: string): void {
    state.value.recipientName = value
  }

  function setRecipientEmail(value: string): void {
    state.value.recipientEmail = value
  }

  function setSenderName(value: string): void {
    state.value.senderName = value
  }

  function setMessage(value: string): void {
    state.value.message = value.slice(0, MAX_MESSAGE_LENGTH)
  }

  function resetErrors(): void {
    errors.value = {}
  }

  function reset(): void {
    state.value = defaultState()
    errors.value = {}
  }

  function validate(): boolean {
    const next: GiftCardComposerErrors = {}

    if (state.value.amountCents < MIN_AMOUNT_CENTS) {
      next.amount = `Minimum is $${MIN_AMOUNT_CENTS / 100}.`
    } else if (state.value.amountCents > MAX_AMOUNT_CENTS) {
      next.amount = `Maximum is $${MAX_AMOUNT_CENTS / 100}.`
    }

    if (!state.value.recipientName.trim()) {
      next.recipientName = 'Recipient name is required.'
    }
    if (!state.value.recipientEmail.trim()) {
      next.recipientEmail = 'Recipient email is required.'
    } else if (!isValidEmail(state.value.recipientEmail)) {
      next.recipientEmail = 'Enter a valid email address.'
    }
    if (!state.value.senderName.trim()) {
      next.senderName = 'Your name is required.'
    }
    if (state.value.schedule === 'custom' && !state.value.scheduleDateIso) {
      next.scheduleDateIso = 'Pick a delivery date.'
    }

    errors.value = next
    return Object.keys(next).length === 0
  }

  /** Resolve the chip slug to an absolute ISO timestamp; null sends immediately. */
  function resolveScheduledSendAt(now: Date = new Date()): string | null {
    switch (state.value.schedule) {
      case 'now':
        return null
      case 'tomorrow':
        return tomorrowAt9(now).toISOString()
      case 'weekend':
        return nextSaturday(now).toISOString()
      case 'custom':
        return state.value.scheduleDateIso
    }
  }

  return {
    state,
    errors,
    setAmountCents,
    setEdition,
    setDeliveryMethod,
    setSchedule,
    setRecipientName,
    setRecipientEmail,
    setSenderName,
    setMessage,
    resetErrors,
    reset,
    validate,
    resolveScheduledSendAt,
  }
}

/** Next Saturday at 9:00 AM local time, used by the "weekend" schedule chip. */
export function nextSaturday(now: Date = new Date()): Date {
  const d = new Date(now)
  const day = d.getDay() // 0 = Sun ... 6 = Sat
  const delta = day === 6 ? 7 : (6 - day + 7) % 7 || 7
  d.setDate(d.getDate() + delta)
  d.setHours(9, 0, 0, 0)
  return d
}

function tomorrowAt9(now: Date = new Date()): Date {
  const d = new Date(now)
  d.setDate(d.getDate() + 1)
  d.setHours(9, 0, 0, 0)
  return d
}

/**
 * Long-form schedule label used by the message-preview stamp ("Tomorrow ·
 * 9:00 AM", "Sat, May 10"). The "now" case is intentionally short to match
 * the design's compact stamp typography.
 */
export function scheduleLongLabel(
  schedule: ScheduleSlug,
  scheduleDateIso: string | null,
  now: Date = new Date(),
): string {
  switch (schedule) {
    case 'now':
      return 'Now'
    case 'tomorrow':
      return 'Tomorrow · 9:00 AM'
    case 'weekend':
      return nextSaturday(now).toLocaleDateString('en-US', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
      })
    case 'custom':
      if (!scheduleDateIso) return 'Custom date'
      return new Date(scheduleDateIso).toLocaleDateString('en-US', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
      })
  }
}

/**
 * Short-form schedule label used by the order summary row ("Now",
 * "Tomorrow", "Sat 10"). Trimmed of preposition / time so it pairs with the
 * delivery-method prefix without overflowing.
 */
export function scheduleShortLabel(
  schedule: ScheduleSlug,
  scheduleDateIso: string | null,
  now: Date = new Date(),
): string {
  switch (schedule) {
    case 'now':
      return 'Now'
    case 'tomorrow':
      return 'Tomorrow'
    case 'weekend':
      return nextSaturday(now).toLocaleDateString('en-US', {
        weekday: 'short',
        day: 'numeric',
      })
    case 'custom':
      if (!scheduleDateIso) return 'Custom'
      return new Date(scheduleDateIso).toLocaleDateString('en-US', {
        weekday: 'short',
        day: 'numeric',
      })
  }
}
