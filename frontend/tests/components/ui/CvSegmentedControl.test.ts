import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvSegmentedControl from '~/components/ui/CvSegmentedControl.vue'

const options = [
  { value: 'month', label: 'Month' },
  { value: 'week', label: 'Week' },
  { value: 'list', label: 'List' },
]

describe('CvSegmentedControl', () => {
  it('renders one button per option', async () => {
    const wrapper = await mountSuspended(CvSegmentedControl, {
      props: { modelValue: 'month', options },
    })
    expect(wrapper.findAll('.cv-segmented__btn')).toHaveLength(3)
  })

  it('marks the active option with aria-selected=true and modifier class', async () => {
    const wrapper = await mountSuspended(CvSegmentedControl, {
      props: { modelValue: 'week', options },
    })
    const buttons = wrapper.findAll('.cv-segmented__btn')
    expect(buttons[0]?.attributes('aria-selected')).toBe('false')
    expect(buttons[1]?.attributes('aria-selected')).toBe('true')
    expect(buttons[1]?.classes()).toContain('cv-segmented__btn--active')
  })

  it('emits update:modelValue when a different option is clicked', async () => {
    const wrapper = await mountSuspended(CvSegmentedControl, {
      props: { modelValue: 'month', options },
    })
    await wrapper.findAll('.cv-segmented__btn')[2]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['list'])
  })

  it('does not re-emit when the active option is clicked again', async () => {
    const wrapper = await mountSuspended(CvSegmentedControl, {
      props: { modelValue: 'month', options },
    })
    await wrapper.findAll('.cv-segmented__btn')[0]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('skips disabled options when arrow-keying', async () => {
    const wrapper = await mountSuspended(CvSegmentedControl, {
      props: {
        modelValue: 'month',
        options: [
          { value: 'month', label: 'Month' },
          { value: 'week', label: 'Week', disabled: true },
          { value: 'list', label: 'List' },
        ],
      },
    })
    await wrapper.findAll('.cv-segmented__btn')[0]!.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['list'])
  })

  it('does not emit when a disabled option is clicked', async () => {
    const wrapper = await mountSuspended(CvSegmentedControl, {
      props: {
        modelValue: 'month',
        options: [
          { value: 'month', label: 'Month' },
          { value: 'week', label: 'Week', disabled: true },
        ],
      },
    })
    await wrapper.findAll('.cv-segmented__btn')[1]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('exposes role=tablist on the wrapper', async () => {
    const wrapper = await mountSuspended(CvSegmentedControl, {
      props: { modelValue: 'month', options, label: 'View' },
    })
    expect(wrapper.attributes('role')).toBe('tablist')
    expect(wrapper.attributes('aria-label')).toBe('View')
  })
})
