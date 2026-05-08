import { describe, it, expect, beforeEach } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import GiftCardComposer from '~/components/content/GiftCardComposer.vue'
import { useGiftCardComposer } from '~/composables/useGiftCardComposer'

beforeEach(() => {
  // Reset the page-scoped composable state between tests so each starts fresh.
  useGiftCardComposer().reset()
})

describe('GiftCardComposer', () => {
  it('renders five preset denominations and a custom-amount row', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const presets = wrapper.findAll('.composer__amount')
    expect(presets).toHaveLength(5)
    expect(presets[0].text()).toContain('$25')
    expect(presets[1].text()).toContain('$50')
    expect(presets[4].text()).toContain('$200')
    expect(wrapper.find('.composer__custom-input').exists()).toBe(true)
  })

  it('marks the matching preset active when state matches', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const presets = wrapper.findAll('.composer__amount')
    expect(presets[1].classes()).toContain('composer__amount--active')
  })

  it('clicking a preset updates composable state', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const composer = useGiftCardComposer()
    await wrapper.findAll('.composer__amount')[2].trigger('click')
    expect(composer.state.value.amountCents).toBe(7500)
  })

  it('typing in custom-amount input updates state and clamps at $500', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const composer = useGiftCardComposer()
    const input = wrapper.find('.composer__custom-input')
    await input.setValue('1000')
    expect(composer.state.value.amountCents).toBe(50000)
  })

  it('clicking an edition swatch updates state', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const composer = useGiftCardComposer()
    await wrapper.find('.composer__swatch--gold').trigger('click')
    expect(composer.state.value.edition).toBe('gold')
  })

  it('clicking the printed delivery option updates state', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const composer = useGiftCardComposer()
    const opts = wrapper.findAll('.composer__delivery-opt')
    await opts[1].trigger('click')
    expect(composer.state.value.deliveryMethod).toBe('print')
  })

  it('clicking a schedule chip updates state', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const composer = useGiftCardComposer()
    const chips = wrapper.findAll('.composer__chip')
    await chips[1].trigger('click') // tomorrow
    expect(composer.state.value.schedule).toBe('tomorrow')
  })

  it('typing in fields updates the shared composable state', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    const composer = useGiftCardComposer()
    await wrapper.find('#gc-recipient-name').setValue('Margot')
    await wrapper.find('#gc-recipient-email').setValue('margot@example.com')
    await wrapper.find('#gc-sender-name').setValue('Henri')
    expect(composer.state.value.recipientName).toBe('Margot')
    expect(composer.state.value.recipientEmail).toBe('margot@example.com')
    expect(composer.state.value.senderName).toBe('Henri')
  })

  it('renders schedule chip with disabled custom-date input until selected', async () => {
    const wrapper = await mountSuspended(GiftCardComposer)
    expect(wrapper.find('.composer__custom-date').exists()).toBe(false)
    await wrapper.findAll('.composer__chip')[3].trigger('click') // Pick…
    expect(wrapper.find('.composer__custom-date').exists()).toBe(true)
  })
})
