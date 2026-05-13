/**
 * Shared state for the purchase step indicator.
 * Pages set step data; the purchase layout reads it and renders the component.
 *
 * Steps: 1 Seats · 2 Payment · 3 Confirmation. Concessions live on the
 * /food-drink browse page, not in the booking flow.
 */
export type PurchaseStep = 1 | 2 | 3

export function usePurchaseStep() {
  const currentStep = useState<PurchaseStep>('purchase-step', () => 1)
  const completedSteps = useState<PurchaseStep[]>('purchase-completed-steps', () => [])
  const navigableSteps = useState<PurchaseStep[]>('purchase-navigable-steps', () => [])

  function setStep(
    step: PurchaseStep,
    completed: readonly PurchaseStep[] = [],
    navigable: readonly PurchaseStep[] = [],
  ) {
    currentStep.value = step
    completedSteps.value = [...completed]
    navigableSteps.value = [...navigable]
  }

  return {
    currentStep: readonly(currentStep),
    completedSteps: readonly(completedSteps),
    navigableSteps: readonly(navigableSteps),
    setStep,
  }
}
