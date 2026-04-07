import type { Meta, StoryObj } from '@storybook/vue3'
import PurchaseStepIndicator from './PurchaseStepIndicator.vue'

const meta: Meta<typeof PurchaseStepIndicator> = {
  title: 'Booking/PurchaseStepIndicator',
  component: PurchaseStepIndicator,
}

export default meta
type Story = StoryObj<typeof PurchaseStepIndicator>

export const Step1_SeatSelection: Story = {
  render: () => ({
    components: { PurchaseStepIndicator },
    template: '<PurchaseStepIndicator :current-step="1" :completed-steps="[]" />',
  }),
}

export const Step2_Checkout: Story = {
  render: () => ({
    components: { PurchaseStepIndicator },
    template: '<PurchaseStepIndicator :current-step="2" :completed-steps="[1]" />',
  }),
}

export const Step3_Confirmation: Story = {
  render: () => ({
    components: { PurchaseStepIndicator },
    template: '<PurchaseStepIndicator :current-step="3" :completed-steps="[1, 2]" :navigable-steps="[]" />',
  }),
}

export const AllStates: Story = {
  render: () => ({
    components: { PurchaseStepIndicator },
    template: `
      <div style="display: flex; flex-direction: column; gap: var(--space-xl);">
        <div>
          <p style="color: var(--tertiary); font-family: var(--font-body); font-size: var(--type-label-md); margin-bottom: var(--space-sm);">Step 1: Seat Selection</p>
          <PurchaseStepIndicator :current-step="1" :completed-steps="[]" />
        </div>
        <div>
          <p style="color: var(--tertiary); font-family: var(--font-body); font-size: var(--type-label-md); margin-bottom: var(--space-sm);">Step 2: Checkout (step 1 navigable)</p>
          <PurchaseStepIndicator :current-step="2" :completed-steps="[1]" />
        </div>
        <div>
          <p style="color: var(--tertiary); font-family: var(--font-body); font-size: var(--type-label-md); margin-bottom: var(--space-sm);">Step 3: Confirmation (non-navigable)</p>
          <PurchaseStepIndicator :current-step="3" :completed-steps="[1, 2]" :navigable-steps="[]" />
        </div>
      </div>
    `,
  }),
}
