import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvCheckbox from '~/components/ui/CvCheckbox.vue'

describe('CvCheckbox', () => {
  it('emits update:modelValue when toggled', async () => {
    const wrapper = await mountSuspended(CvCheckbox, {
      props: { modelValue: false, label: 'Accept terms' },
    })
    const input = wrapper.find('input[type="checkbox"]')
    await input.trigger('change')
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([true])
  })

  it('shows checked visual when modelValue is true', async () => {
    const wrapper = await mountSuspended(CvCheckbox, {
      props: { modelValue: true, label: 'Accept' },
    })
    expect(wrapper.find('.cv-checkbox--checked').exists()).toBe(true)
    expect(wrapper.find('.cv-checkbox__indicator svg').exists()).toBe(true)
  })

  it('does not show check icon when unchecked', async () => {
    const wrapper = await mountSuspended(CvCheckbox, {
      props: { modelValue: false, label: 'Accept' },
    })
    expect(wrapper.find('.cv-checkbox__indicator svg').exists()).toBe(false)
  })

  it('does not toggle when disabled', async () => {
    const wrapper = await mountSuspended(CvCheckbox, {
      props: { modelValue: false, label: 'Accept', disabled: true },
    })
    const input = wrapper.find('input[type="checkbox"]')
    await input.trigger('change')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('uses inline layout via CvFormField', async () => {
    const wrapper = await mountSuspended(CvCheckbox, {
      props: { modelValue: false, label: 'Accept terms' },
    })
    expect(wrapper.find('.cv-form-field--inline').exists()).toBe(true)
  })
})
