import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvInput from '~/components/ui/CvInput.vue'

describe('CvInput', () => {
  it('emits update:modelValue on input', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: '', label: 'Name' },
    })
    const input = wrapper.find('input')
    await input.setValue('John')
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['John'])
  })

  it('sets aria-invalid when error is provided', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: '', label: 'Email', error: 'Invalid' },
    })
    const input = wrapper.find('input')
    expect(input.attributes('aria-invalid')).toBe('true')
  })

  it('sets aria-describedby linking to error', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: '', label: 'Email', error: 'Invalid email' },
    })
    const input = wrapper.find('input')
    const errorEl = wrapper.find('.cv-form-field__error')
    expect(input.attributes('aria-describedby')).toContain(errorEl.attributes('id'))
  })

  it('sets aria-required when required', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: '', label: 'Name', required: true },
    })
    const input = wrapper.find('input')
    expect(input.attributes('aria-required')).toBe('true')
  })

  it('emits focus and blur events', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: '', label: 'Name' },
    })
    const input = wrapper.find('input')
    await input.trigger('focus')
    await input.trigger('blur')
    expect(wrapper.emitted('focus')).toHaveLength(1)
    expect(wrapper.emitted('blur')).toHaveLength(1)
  })

  it('has label with matching for/id', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: '', label: 'Username' },
    })
    const label = wrapper.find('label')
    const input = wrapper.find('input')
    expect(label.attributes('for')).toBe(input.attributes('id'))
  })

  it('emits number type for number input', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: 0, label: 'Quantity', type: 'number' },
    })
    const input = wrapper.find('input')
    await input.setValue('42')
    const emitted = wrapper.emitted('update:modelValue')![0][0]
    expect(emitted).toBe(42)
    expect(typeof emitted).toBe('number')
  })

  it('does not emit 0 when clearing a number input', async () => {
    const wrapper = await mountSuspended(CvInput, {
      props: { modelValue: 5, label: 'Quantity', type: 'number' },
    })
    const input = wrapper.find('input')
    await input.setValue('')
    const emitted = wrapper.emitted('update:modelValue')![0][0]
    expect(emitted).not.toBe(0)
  })
})
