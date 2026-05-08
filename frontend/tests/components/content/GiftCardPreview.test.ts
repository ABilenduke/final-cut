import { describe, it, expect, beforeEach } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import GiftCardPreview from '~/components/content/GiftCardPreview.vue'
import { useGiftCardComposer } from '~/composables/useGiftCardComposer'

beforeEach(() => {
  const composer = useGiftCardComposer()
  composer.reset()
  composer.state.value.recipientName = 'Margot Renard'
  composer.state.value.recipientEmail = 'margot@example.com'
  composer.state.value.senderName = 'Henri'
  composer.state.value.message = 'For the long Sunday afternoons. — H.'
})

describe('GiftCardPreview', () => {
  it('renders the gift card visual with the current amount and edition', async () => {
    const wrapper = await mountSuspended(GiftCardPreview)
    const visual = wrapper.find('.gift-card-visual')
    expect(visual.exists()).toBe(true)
    expect(visual.attributes('data-variant')).toBe('reactor')
  })

  it('renders the live message preview with current state', async () => {
    const wrapper = await mountSuspended(GiftCardPreview)
    expect(wrapper.find('.gift-card-preview__msg-salutation').text()).toContain('Margot Renard')
    expect(wrapper.find('.gift-card-preview__msg-body').text()).toContain('long Sunday afternoons')
    expect(wrapper.find('.gift-card-preview__msg-signoff').text()).toContain('Henri')
  })

  it('shows order summary with edition and delivery labels', async () => {
    const wrapper = await mountSuspended(GiftCardPreview)
    const text = wrapper.text()
    expect(text).toContain('Reactor')
    expect(text).toContain('Email · Now')
    expect(text).toContain('$50.00')
  })

  it('uses fallback labels when fields are empty', async () => {
    const composer = useGiftCardComposer()
    composer.state.value.recipientName = ''
    composer.state.value.senderName = ''
    composer.state.value.message = ''
    const wrapper = await mountSuspended(GiftCardPreview)
    expect(wrapper.find('.gift-card-preview__msg-salutation').text()).toContain('A friend')
    expect(wrapper.find('.gift-card-preview__msg-body').text()).toContain('Enjoy the films.')
    expect(wrapper.find('.gift-card-preview__msg-signoff').text()).toContain('A patron')
  })

  it('emits submit with the full composer payload when valid', async () => {
    const wrapper = await mountSuspended(GiftCardPreview)
    await wrapper.find('.gift-card-preview__btn').trigger('click')
    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeTruthy()
    expect(emitted![0][0]).toMatchObject({
      amount: 5000,
      recipientEmail: 'margot@example.com',
      recipientName: 'Margot Renard',
      senderName: 'Henri',
      edition: 'reactor',
      deliveryMethod: 'email',
      scheduledSendAt: null,
    })
  })

  it('does not emit submit when validation fails', async () => {
    const composer = useGiftCardComposer()
    composer.state.value.recipientName = ''
    composer.state.value.recipientEmail = ''
    composer.state.value.senderName = ''
    const wrapper = await mountSuspended(GiftCardPreview)
    await wrapper.find('.gift-card-preview__btn').trigger('click')
    expect(wrapper.emitted('submit')).toBeFalsy()
  })
})
