import { describe, it, expect, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvModal from '~/components/ui/CvModal.vue'

// Helper: query teleported modal content inside document.body
function findTeleported(selector: string) {
  return document.body.querySelector(selector)
}

describe('CvModal', () => {
  afterEach(() => {
    // Clean up any teleported DOM and scroll lock between tests
    document.body.style.overflow = ''
    document.querySelectorAll('.cv-modal__backdrop').forEach((el) => el.remove())
  })

  it('renders dialog with correct role and aria attributes when open', async () => {
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Test Modal' },
      slots: { default: 'Modal content' },
    })
    await nextTick()
    const dialog = findTeleported('[role="dialog"]')
    expect(dialog).toBeTruthy()
    expect(dialog!.getAttribute('aria-modal')).toBe('true')
    expect(dialog!.getAttribute('aria-labelledby')).toBeTruthy()
    wrapper.unmount()
  })

  it('does not render when modelValue is false', async () => {
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: false, title: 'Test Modal' },
    })
    expect(findTeleported('[role="dialog"]')).toBeNull()
    wrapper.unmount()
  })

  it('emits update:modelValue false on close button click', async () => {
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Test Modal' },
    })
    await nextTick()
    const closeBtn = findTeleported('.cv-modal__close') as HTMLElement
    expect(closeBtn).toBeTruthy()
    closeBtn.click()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([false])
    wrapper.unmount()
  })

  it('renders title in heading', async () => {
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Confirm Booking' },
    })
    await nextTick()
    const title = findTeleported('.cv-modal__title')
    expect(title).toBeTruthy()
    expect(title!.textContent).toBe('Confirm Booking')
    wrapper.unmount()
  })

  it('renders footer slot when provided', async () => {
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Test' },
      slots: {
        default: 'Body',
        footer: 'Footer Content',
      },
    })
    await nextTick()
    const footer = findTeleported('.cv-modal__footer')
    expect(footer).toBeTruthy()
    expect(footer!.textContent).toBe('Footer Content')
    wrapper.unmount()
  })

  it('does not show close button when closeable is false', async () => {
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Processing', closeable: false },
    })
    await nextTick()
    expect(findTeleported('.cv-modal__close')).toBeNull()
    wrapper.unmount()
  })

  it('locks scroll when mounted with modelValue true', async () => {
    document.body.style.overflow = ''
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Initial Open' },
    })
    await nextTick()
    await nextTick()
    expect(document.body.style.overflow).toBe('hidden')
    wrapper.unmount()
  })

  it('handles Escape when mounted with modelValue true', async () => {
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Initial Open' },
    })
    await nextTick()
    await nextTick()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([false])
    wrapper.unmount()
  })

  it('restores scroll on unmount after initial open', async () => {
    document.body.style.overflow = ''
    const wrapper = await mountSuspended(CvModal, {
      props: { modelValue: true, title: 'Initial Open' },
    })
    await nextTick()
    await nextTick()
    expect(document.body.style.overflow).toBe('hidden')
    wrapper.unmount()
    expect(document.body.style.overflow).toBe('')
  })
})
