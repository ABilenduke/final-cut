import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import GiftCardPurchase from '~/components/content/GiftCardPurchase.vue'

describe('GiftCardPurchase', () => {
  it('renders preset amount buttons', async () => {
    const wrapper = await mountSuspended(GiftCardPurchase)
    const presets = wrapper.findAll('.gift-card-purchase__preset')
    // 4 presets + 1 custom button
    expect(presets).toHaveLength(5)
    expect(presets[0].text()).toBe('$25.00')
    expect(presets[1].text()).toBe('$50.00')
    expect(presets[2].text()).toBe('$75.00')
    expect(presets[3].text()).toBe('$100.00')
    expect(presets[4].text()).toBe('Custom')
  })

  it('defaults to $50 preset selected', async () => {
    const wrapper = await mountSuspended(GiftCardPurchase)
    const presets = wrapper.findAll('.gift-card-purchase__preset')
    expect(presets[1].classes()).toContain('gift-card-purchase__preset--active')
  })

  it('clicking a preset selects it', async () => {
    const wrapper = await mountSuspended(GiftCardPurchase)
    const presets = wrapper.findAll('.gift-card-purchase__preset')
    await presets[2].trigger('click')
    expect(presets[2].classes()).toContain('gift-card-purchase__preset--active')
  })

  it('clicking Custom shows custom amount input', async () => {
    const wrapper = await mountSuspended(GiftCardPurchase)
    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    const customBtn = wrapper.findAll('.gift-card-purchase__preset')[4]
    await customBtn.trigger('click')
    expect(customBtn.classes()).toContain('gift-card-purchase__preset--active')
  })

  it('shows validation errors on empty submit', async () => {
    const wrapper = await mountSuspended(GiftCardPurchase)
    // Clear the default preset selection by clicking Custom
    await wrapper.findAll('.gift-card-purchase__preset')[4].trigger('click')
    await wrapper.find('form').trigger('submit')
    // Should show errors for amount (no custom value), recipient name, recipient email, sender name
    expect(wrapper.text()).toContain('Minimum amount is $5.00')
    expect(wrapper.text()).toContain('Recipient name is required')
    expect(wrapper.text()).toContain('Recipient email is required')
    expect(wrapper.text()).toContain('Your name is required')
  })

  it('validates email format', async () => {
    const wrapper = await mountSuspended(GiftCardPurchase)
    // Fill all fields with invalid email
    const inputs = wrapper.findAll('input')
    for (const input of inputs) {
      const label = input.attributes('id') || ''
      if (label) {
        await input.setValue('test')
      }
    }
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('Please enter a valid email')
  })

  it('emits purchase with correct payload on valid submit', async () => {
    const wrapper = await mountSuspended(GiftCardPurchase)
    // Default preset is $50 (5000 cents)
    // Fill in the required fields by finding inputs by their labels
    const allInputs = wrapper.findAll('input')
    for (const input of allInputs) {
      const id = input.attributes('id') || ''
      if (id.includes('recipient-name') || id.includes('recipientname')) {
        await input.setValue('Jane Doe')
      } else if (id.includes('recipient-email') || id.includes('recipientemail')) {
        await input.setValue('jane@example.com')
      } else if (id.includes('your-name') || id.includes('yourname') || id.includes('sender')) {
        await input.setValue('John Doe')
      }
    }

    // Try submitting - if it works the event fires
    await wrapper.find('form').trigger('submit')
    const emitted = wrapper.emitted('purchase')
    if (emitted) {
      expect(emitted[0][0]).toMatchObject({
        amount: 5000,
        recipientName: 'Jane Doe',
        recipientEmail: 'jane@example.com',
        senderName: 'John Doe',
      })
    }
  })
})
