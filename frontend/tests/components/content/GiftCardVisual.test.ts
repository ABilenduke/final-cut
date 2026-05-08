import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import GiftCardVisual from '~/components/content/GiftCardVisual.vue'

describe('GiftCardVisual', () => {
  it('renders the amount with currency and decimal split', async () => {
    const wrapper = await mountSuspended(GiftCardVisual, {
      props: { amountCents: 7500, edition: 'reactor' },
    })
    const amount = wrapper.find('.gift-card-visual__amount')
    expect(amount.text()).toContain('$')
    expect(amount.text()).toContain('75')
    expect(amount.text()).toContain('.00')
  })

  it('formats odd cent amounts correctly', async () => {
    const wrapper = await mountSuspended(GiftCardVisual, {
      props: { amountCents: 4250, edition: 'reactor' },
    })
    const amount = wrapper.find('.gift-card-visual__amount')
    expect(amount.text()).toContain('42')
    expect(amount.text()).toContain('.50')
  })

  it('reflects the edition in the data-variant attribute', async () => {
    const wrapper = await mountSuspended(GiftCardVisual, {
      props: { amountCents: 5000, edition: 'gold' },
    })
    expect(wrapper.find('.gift-card-visual').attributes('data-variant')).toBe('gold')
  })

  it('renders an aria-label that names the edition and total', async () => {
    const wrapper = await mountSuspended(GiftCardVisual, {
      props: { amountCents: 10000, edition: 'void' },
    })
    const aria = wrapper.find('.gift-card-visual').attributes('aria-label')
    expect(aria).toContain('Pure Void')
    expect(aria).toContain('$100.00')
  })

  it('uses the supplied serial and volume props', async () => {
    const wrapper = await mountSuspended(GiftCardVisual, {
      props: {
        amountCents: 5000,
        edition: 'reactor',
        serial: 'FC—DEAD · BEEF · CAFE',
        volume: 'Vol. XXIV',
      },
    })
    expect(wrapper.find('.gift-card-visual__serial').text()).toBe('FC—DEAD · BEEF · CAFE')
    expect(wrapper.find('.gift-card-visual__corner-tr').text()).toBe('Vol. XXIV')
  })
})
