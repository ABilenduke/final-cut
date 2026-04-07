import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvTextarea from '~/components/ui/CvTextarea.vue'

describe('CvTextarea', () => {
  it('emits update:modelValue on input', async () => {
    const wrapper = await mountSuspended(CvTextarea, {
      props: { modelValue: '', label: 'Message' },
    })
    const textarea = wrapper.find('textarea')
    await textarea.setValue('Hello')
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['Hello'])
  })

  it('applies rows prop', async () => {
    const wrapper = await mountSuspended(CvTextarea, {
      props: { modelValue: '', label: 'Message', rows: 6 },
    })
    const textarea = wrapper.find('textarea')
    expect(textarea.attributes('rows')).toBe('6')
  })

  it('has matching label for/id', async () => {
    const wrapper = await mountSuspended(CvTextarea, {
      props: { modelValue: '', label: 'Notes' },
    })
    const label = wrapper.find('label')
    const textarea = wrapper.find('textarea')
    expect(label.attributes('for')).toBe(textarea.attributes('id'))
  })

  it('shows error via CvFormField', async () => {
    const wrapper = await mountSuspended(CvTextarea, {
      props: { modelValue: '', label: 'Message', error: 'Required' },
    })
    expect(wrapper.find('.cv-form-field__error').text()).toBe('Required')
    expect(wrapper.find('textarea').attributes('aria-invalid')).toBe('true')
  })
})
