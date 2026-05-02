import { describe, it, expect, vi, afterEach } from 'vitest'
import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import CheckoutPaymentBay from '~/components/booking/CheckoutPaymentBay.vue'

const elementsMock = vi.fn()
const createMock = vi.fn()
const mountMock = vi.fn()
const onMock = vi.fn()

vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn(() => Promise.resolve(null)),
}))

mockNuxtImport('useRuntimeConfig', () => {
  return () => ({ public: { stripePublishableKey: 'pk_test_design_token_check' } })
})

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

  it('shows the billing country selector with US default', async () => {
    const wrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    const select = wrapper.find<HTMLSelectElement>('.payment-bay__country')
    expect(select.element.value).toBe('US')
  })

  it('shows the loyalty checkbox for guests and save-card for authenticated users', async () => {
    const guestWrapper = await mountSuspended(CheckoutPaymentBay, {
      props: { email: '', isAuthenticated: false },
    })
    expect(guestWrapper.text()).toContain('Final Cut Rewards')

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
  afterEach(() => {
    vi.restoreAllMocks()
    elementsMock.mockReset()
    createMock.mockReset()
    mountMock.mockReset()
    onMock.mockReset()
  })

  it('passes CSS-variable values into stripe.elements() appearance and card style', async () => {
    // Stub the design tokens on documentElement so getComputedStyle returns predictable values.
    const tokens: Record<string, string> = {
      '--secondary': '#DAC769',
      '--surface-container-low': '#1c1b1b',
      '--on-surface': '#E5E2E1',
      '--primary': '#FFB4A8',
      '--on-tertiary-fixed-variant': '#A89F91',
      '--radius-sm': '0.125rem',
    }
    for (const [name, value] of Object.entries(tokens)) {
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
    // Allow onMounted's async loadStripe chain to settle.
    await new Promise((resolve) => setTimeout(resolve, 0))

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
