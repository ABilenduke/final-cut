/**
 * Shared state for purchase step indicator.
 * Pages set step data; the purchase layout reads it and renders the component.
 */
export function usePurchaseStep() {
  const currentStep = useState<1 | 2 | 3>('purchase-step', () => 1)
  const completedSteps = useState<number[]>('purchase-completed-steps', () => [])
  const navigableSteps = useState<number[]>('purchase-navigable-steps', () => [])

  function setStep(step: 1 | 2 | 3, completed: number[] = [], navigable: number[] = []) {
    currentStep.value = step
    completedSteps.value = completed
    navigableSteps.value = navigable
  }

  return {
    currentStep: readonly(currentStep),
    completedSteps: readonly(completedSteps),
    navigableSteps: readonly(navigableSteps),
    setStep,
  }
}
