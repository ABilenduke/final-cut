import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import PurchaseStepIndicator from '~/components/booking/PurchaseStepIndicator.vue'

describe('PurchaseStepIndicator', () => {
  it('renders three steps', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 1, completedSteps: [] },
    })
    const items = wrapper.findAll('.purchase-steps__item')
    expect(items).toHaveLength(3)
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
      props: { currentStep: 2, completedSteps: [1] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    expect(steps[0].classes()).toContain('purchase-steps__step--completed')
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
    // Step 1 is completed and navigable (defaults to completedSteps)
    expect(steps[0].element.tagName).toBe('BUTTON')
    // Step 2 is current — rendered as span
    expect(steps[1].element.tagName).toBe('SPAN')
  })

  it('renders non-navigable steps as spans', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 3, completedSteps: [1, 2], navigableSteps: [] },
    })
    const steps = wrapper.findAll('.purchase-steps__step')
    // On confirmation page, all steps are non-navigable
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
      props: { currentStep: 3, completedSteps: [1, 2], navigableSteps: [] },
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
    // Steps 1 and 2 should have CvIcon (check), not step numbers
    expect(numbers[0].text()).not.toContain('1')
    expect(numbers[1].text()).not.toContain('2')
    // Step 3 is current, shows number
    expect(numbers[2].text()).toContain('3')
  })

  it('highlights connector when previous step is completed', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 2, completedSteps: [1] },
    })
    const connectors = wrapper.findAll('.purchase-steps__connector')
    // Connector before step 2: step 1 is completed → highlighted
    expect(connectors[0].classes()).toContain('purchase-steps__connector--completed')
    // Connector before step 3: step 2 is not completed → not highlighted
    expect(connectors[1].classes()).not.toContain('purchase-steps__connector--completed')
  })

  it('highlights both connectors when steps 1 and 2 are completed', async () => {
    const wrapper = await mountSuspended(PurchaseStepIndicator, {
      props: { currentStep: 3, completedSteps: [1, 2] },
    })
    const connectors = wrapper.findAll('.purchase-steps__connector')
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
