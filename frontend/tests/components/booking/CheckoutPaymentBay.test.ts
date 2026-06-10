import { describe, it, expect, vi, afterEach } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'

// Mocks must be declared before the SFC import so `useRuntimeConfig` is
// already stubbed when the component module loads its top-level call.
mockNuxtImport('useRuntimeConfig', () => {
  return () => ({ public: { stripePublishableKey: 'pk_test_design_token_check' } })
})

vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn(() => Promise.resolve(null)),
}))

// Saved-card lookup (admin-v5 Plan 03) — empty list keeps these UI tests
// exercising the card-element layout.
vi.mock('~/utils/api', () => ({
  apiFetch: vi.fn(() => Promise.resolve({ data: [] })),
  useApiFetch: vi.fn(),
}))

import CheckoutPaymentBay from '~/components/booking/CheckoutPaymentBay.vue'

const elementsMock = vi.fn()
const createMock = vi.fn()
const mountMock = vi.fn()
const onMock = vi.fn()

describe('CheckoutPaymentBay', () => {
  it('renders the §02 numbered header with PCI-DSS badge', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    expect(wrapper.find('.bay__number').text()).toBe('§ 02')
    expect(wrapper.text()).toContain('PCI-DSS')
  })

  it('renders four payment method tabs with card active', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    const tabs = wrapper.findAll('.method')
    expect(tabs).toHaveLength(4)
    expect(tabs[0].classes()).toContain('method--active')
    expect(tabs[0].text()).toContain('Card')
    expect(tabs[1].text()).toContain('PayPal')
    expect(tabs[2].text()).toContain('Gift Card')
    expect(tabs[3].text()).toContain('Pay on Arrival')
  })

  it('marks non-card tabs as disabled', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    const disabledTabs = wrapper.findAll('.method--disabled')
    expect(disabledTabs).toHaveLength(3)
    expect(disabledTabs[0].attributes('disabled')).toBeDefined()
  })

  it('exposes the payment methods as a button group, not a broken tab widget', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    // role="group" (not tablist) — a 1-of-4 tablist with no tabpanel is invalid ARIA.
    expect(wrapper.find('[role="group"][aria-label="Payment method"]').exists()).toBe(true)
    expect(wrapper.findAll('[role="tab"]')).toHaveLength(0)
    // Active method is conveyed via aria-pressed on the enabled (card) button.
    expect(wrapper.find('.method--active').attributes('aria-pressed')).toBe('true')
  })

  it('shows the billing country selector with US default', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    const select = wrapper.find<HTMLSelectElement>('.payment-bay__country')
    expect(select.element.value).toBe('US')
  })

  it('shows save-card for authenticated users only — the unwired loyalty opt-in is gone', async () => {
    const guestWrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    // The "Join Final Cut Rewards" checkbox was removed (admin-v3 Plan 04):
    // the backend never read it, so the checkbox promised something the
    // purchase didn't do. Guests see no opt-in until the magic-link claim
    // flow ships.
    expect(guestWrapper.text()).not.toContain('Final Cut Rewards')

    const authWrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: 'a@b.c', isAuthenticated: true },
    })
    expect(authWrapper.text()).toContain('Save this card')
  })

  it('exposes a submit method via defineExpose', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    // defineExpose makes `submit` available on the component instance.
    expect(typeof (wrapper.vm as unknown as { submit: unknown }).submit).toBe('function')
  })
})

describe('CheckoutPaymentBay — Stripe appearance reads design tokens', () => {
  // jsdom's documentElement is shared across `it` blocks; without restoring
  // prior values, tokens set here leak into later tests.
  const tokens: Record<string, string> = {
    '--secondary': '#DAC769',
    '--surface-container-low': '#1c1b1b',
    '--on-surface': '#E5E2E1',
    '--primary': '#FFB4A8',
    '--on-tertiary-fixed-variant': '#A89F91',
    '--radius-sm': '0.125rem',
  }
  let priorTokens: Record<string, string> = {}

  afterEach(() => {
    for (const [name, prior] of Object.entries(priorTokens)) {
      if (prior === '') {
        document.documentElement.style.removeProperty(name)
      } else {
        document.documentElement.style.setProperty(name, prior)
      }
    }
    priorTokens = {}

    vi.restoreAllMocks()
    elementsMock.mockReset()
    createMock.mockReset()
    mountMock.mockReset()
    onMock.mockReset()
  })

  it('passes CSS-variable values into stripe.elements() appearance and card style', async () => {
    for (const [name, value] of Object.entries(tokens)) {
      priorTokens[name] = document.documentElement.style.getPropertyValue(name)
      document.documentElement.style.setProperty(name, value)
    }

    // Wire mock Stripe.elements API so we can intercept the appearance config.
    createMock.mockReturnValue({ mount: mountMock, on: onMock, destroy: vi.fn() })
    elementsMock.mockReturnValue({ create: createMock })
    const fakeStripe = { elements: elementsMock }

    const stripeJs = await import('@stripe/stripe-js')
    vi.mocked(stripeJs.loadStripe).mockResolvedValueOnce(
      fakeStripe as unknown as Awaited<ReturnType<typeof stripeJs.loadStripe>>,
    )

    await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    await vi.waitFor(() => expect(elementsMock).toHaveBeenCalled(), { timeout: 1000 })

    expect(elementsMock).toHaveBeenCalledTimes(1)
    const appearanceConfig = elementsMock.mock.calls[0]![0]!
    expect(appearanceConfig.appearance.theme).toBe('night')
    expect(appearanceConfig.appearance.variables).toMatchObject({
      colorPrimary: tokens['--secondary'],
      colorBackground: tokens['--surface-container-low'],
      colorText: tokens['--on-surface'],
      colorDanger: tokens['--primary'],
      colorTextPlaceholder: tokens['--on-tertiary-fixed-variant'],
      borderRadius: tokens['--radius-sm'],
    })

    expect(createMock).toHaveBeenCalledTimes(1)
    const [cardType, cardConfig] = createMock.mock.calls[0]!
    expect(cardType).toBe('card')
    expect(cardConfig.style.base.color).toBe(tokens['--on-surface'])
    expect(cardConfig.style.base['::placeholder'].color).toBe(tokens['--on-tertiary-fixed-variant'])
    expect(cardConfig.style.invalid.color).toBe(tokens['--primary'])
  })
})
