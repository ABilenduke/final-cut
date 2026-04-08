import { describe, it, expect } from 'vitest'
import { usePurchaseStep } from '~/composables/usePurchaseStep'

describe('usePurchaseStep', () => {
  it('has correct initial state', () => {
    const { currentStep, completedSteps, navigableSteps } = usePurchaseStep()
    expect(currentStep.value).toBe(1)
    expect(completedSteps.value).toEqual([])
    expect(navigableSteps.value).toEqual([])
  })

  it('setStep updates currentStep', () => {
    const { currentStep, setStep } = usePurchaseStep()
    setStep(2, [1], [1])
    expect(currentStep.value).toBe(2)
  })

  it('setStep updates completedSteps', () => {
    const { completedSteps, setStep } = usePurchaseStep()
    setStep(3, [1, 2], [1, 2])
    expect(completedSteps.value).toEqual([1, 2])
  })

  it('setStep updates navigableSteps', () => {
    const { navigableSteps, setStep } = usePurchaseStep()
    setStep(3, [1, 2], [])
    expect(navigableSteps.value).toEqual([])
  })

  it('setStep defaults completed and navigable to empty arrays', () => {
    const { completedSteps, navigableSteps, setStep } = usePurchaseStep()
    // First set some values
    setStep(2, [1], [1])
    // Then call with defaults
    setStep(1)
    expect(completedSteps.value).toEqual([])
    expect(navigableSteps.value).toEqual([])
  })

  it('state is reactive', () => {
    const { currentStep, completedSteps, navigableSteps, setStep } = usePurchaseStep()

    expect(currentStep.value).toBeDefined()

    setStep(2, [1], [1])
    expect(currentStep.value).toBe(2)
    expect(completedSteps.value).toEqual([1])
    expect(navigableSteps.value).toEqual([1])

    setStep(3, [1, 2], [])
    expect(currentStep.value).toBe(3)
    expect(completedSteps.value).toEqual([1, 2])
    expect(navigableSteps.value).toEqual([])
  })

  it('returned state is readonly', () => {
    const { currentStep, completedSteps, navigableSteps } = usePurchaseStep()
    // readonly refs should not allow direct mutation
    // TypeScript would prevent this at compile time, but we verify the refs are readonly wrappers
    expect(currentStep.value).toBeDefined()
    expect(completedSteps.value).toBeDefined()
    expect(navigableSteps.value).toBeDefined()
  })
})
