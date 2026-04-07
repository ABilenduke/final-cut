import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvSelect from '~/components/ui/CvSelect.vue'

const options = [
  { value: 'a', label: 'Option A' },
  { value: 'b', label: 'Option B' },
  { value: 'c', label: 'Option C' },
]

describe('CvSelect', () => {
  it('renders options', async () => {
    const wrapper = await mountSuspended(CvSelect, {
      props: { modelValue: '', options, label: 'Choice' },
    })
    const optionEls = wrapper.findAll('option')
    // placeholder + 3 options = 4
    expect(optionEls.length).toBeGreaterThanOrEqual(3)
  })

  it('shows placeholder when no value selected', async () => {
    const wrapper = await mountSuspended(CvSelect, {
      props: { modelValue: '', options, label: 'Choice' },
    })
    const placeholder = wrapper.find('option[disabled]')
    expect(placeholder.exists()).toBe(true)
  })

  it('emits update:modelValue on change', async () => {
    const wrapper = await mountSuspended(CvSelect, {
      props: { modelValue: '', options, label: 'Choice' },
    })
    const select = wrapper.find('select')
    await select.setValue('b')
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['b'])
  })

  it('has matching label for/id', async () => {
    const wrapper = await mountSuspended(CvSelect, {
      props: { modelValue: '', options, label: 'Type' },
    })
    const label = wrapper.find('label')
    const select = wrapper.find('select')
    expect(label.attributes('for')).toBe(select.attributes('id'))
  })

  it('emits numeric value as number, not string', async () => {
    const numericOptions = [
      { value: 1, label: 'One' },
      { value: 2, label: 'Two' },
      { value: 3, label: 'Three' },
    ]
    const wrapper = await mountSuspended(CvSelect, {
      props: { modelValue: '', options: numericOptions, label: 'Number' },
    })
    const select = wrapper.find('select')
    await select.setValue('2')
    const emitted = wrapper.emitted('update:modelValue')![0][0]
    expect(emitted).toBe(2)
    expect(typeof emitted).toBe('number')
  })

  it('does not show placeholder when value is 0', async () => {
    const numericOptions = [
      { value: 0, label: 'Zero' },
      { value: 1, label: 'One' },
    ]
    const wrapper = await mountSuspended(CvSelect, {
      props: { modelValue: 0, options: numericOptions, label: 'Number' },
    })
    const placeholder = wrapper.find('option[disabled]')
    expect(placeholder.exists()).toBe(false)
  })

  it('shows placeholder when value is null-ish (empty string)', async () => {
    const wrapper = await mountSuspended(CvSelect, {
      props: { modelValue: '', options, label: 'Choice' },
    })
    const placeholder = wrapper.find('option[disabled]')
    expect(placeholder.exists()).toBe(true)
  })
})
