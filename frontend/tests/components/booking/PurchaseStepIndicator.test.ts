import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import PurchaseStepIndicator from '~/components/booking/PurchaseStepIndicator.vue'

describe('PurchaseStepIndicator', () => {
  it('renders three steps in the new flow', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 1, completedSteps: [] },
    })
    const items = wrapper.findAll('.purchase-steps__item')
    expect(items).toHaveLength(3)
  })

  it('renders the three step labels in order (no concessions step)', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 1, completedSteps: [] },
    })
    const labels = wrapper.findAll('.purchase-steps__label').map(l => l.text())
    expect(labels).toEqual(['Seats', 'Payment', 'Confirmation'])
  })

  it('formats step numbers with two-digit padding (01, 02, 03)', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 2, completedSteps: [1] },
    })
    const numbers = wrapper.findAll('.purchase-steps__number')
    // Step 1 completed → check icon (no digit). Step 2 current → "02".
    expect(numbers[1].text()).toBe('02')
    expect(numbers[2].text()).toBe('03')
  })

  it('marks the current step with aria-current="step"', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 2, completedSteps: [1] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    expect(steps[1].attributes('aria-current')).toBe('step')
    expect(steps[0].attributes('aria-current')).toBeUndefined()
    expect(steps[2].attributes('aria-current')).toBeUndefined()
  })

  it('applies current class to the active step', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 1, completedSteps: [] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    expect(steps[0].classes()).toContain('purchase-steps__step--current')
  })

  it('applies completed class to completed non-current steps', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 3, completedSteps: [1, 2] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    expect(steps[0].classes()).toContain('purchase-steps__step--completed')
    expect(steps[1].classes()).toContain('purchase-steps__step--completed')
    expect(steps[0].classes()).not.toContain('purchase-steps__step--current')
  })

  it('applies future class to steps beyond current', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 1, completedSteps: [] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    expect(steps[1].classes()).toContain('purchase-steps__step--future')
    expect(steps[2].classes()).toContain('purchase-steps__step--future')
  })

  it('renders navigable completed steps as buttons', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 2, completedSteps: [1] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    expect(steps[0].element.tagName).toBe('BUTTON')
    // Step 2 is current → rendered as span
    expect(steps[1].element.tagName).toBe('SPAN')
    expect(steps[2].element.tagName).toBe('SPAN')
  })

  it('renders all steps as spans on the confirmation page (final step, no navigation)', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 3, completedSteps: [1, 2, 3], navigableSteps: [] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    expect(steps[0].element.tagName).toBe('SPAN')
    expect(steps[1].element.tagName).toBe('SPAN')
    expect(steps[2].element.tagName).toBe('SPAN')
  })

  it('emits navigate for navigable non-current steps on click', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 2, completedSteps: [1] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    await steps[0].trigger('click')
    expect(wrapper.emitted('navigate')).toEqual([[1]])
  })

  it('does not emit navigate when clicking the current step', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 2, completedSteps: [1] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    await steps[1].trigger('click')
    expect(wrapper.emitted('navigate')).toBeUndefined()
  })

  it('does not emit navigate for non-navigable steps', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 3, completedSteps: [1, 2, 3], navigableSteps: [] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    await steps[0].trigger('click')
    await steps[1].trigger('click')
    expect(wrapper.emitted('navigate')).toBeUndefined()
  })

  it('shows check icon for completed non-current steps', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 3, completedSteps: [1, 2] },
    })
    const numbers = wrapper.findAll('.purchase-steps__number')
    expect(numbers[0].text()).not.toContain('1')
    expect(numbers[1].text()).not.toContain('2')
    // Step 3 is current → shows "03"
    expect(numbers[2].text()).toBe('03')
  })

  it('highlights connectors between completed steps', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 3, completedSteps: [1, 2] },
    })
    const connectors = wrapper.findAll('.purchase-steps__connector')
    // 3 steps → 2 connectors
    expect(connectors).toHaveLength(2)
    expect(connectors[0].classes()).toContain('purchase-steps__connector--completed')
    expect(connectors[1].classes()).toContain('purchase-steps__connector--completed')
  })

  it('has correct nav landmark', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 1, completedSteps: [] },
    })
    const nav = wrapper.find('nav')
    expect(nav.attributes('aria-label')).toBe('Purchase steps')
  })
})
