/**
 * Shared state for the purchase step indicator.
 * Pages set step data; the purchase layout reads it and renders the component.
 *
 * Steps: 1 Seats · 2 Snacks & Bar · 3 Payment · 4 Confirmation.
 */
export type PurchaseStep = 1 | 2 | 3 | 4

export function usePurchaseStep() {
  const currentStep = useState<PurchaseStep>('purchase-step', () => 1)
  const completedSteps = useState<number[]>('purchase-completed-steps', () => [])
  const navigableSteps = useState<number[]>('purchase-navigable-steps', () => [])

  function setStep(step: PurchaseStep, completed: number[] = [], navigable: number[] = []) {
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
